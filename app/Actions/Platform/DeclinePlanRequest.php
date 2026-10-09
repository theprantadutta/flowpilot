<?php

namespace App\Actions\Platform;

use App\Models\Organization;
use App\Models\PlanChangeRequest;
use App\Models\User;
use App\Notifications\PlanNoticeNotification;
use App\Support\Activity\ActivityLogger;
use App\Support\Billing\BillingContacts;
use App\Support\Tenancy\Tenancy;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class DeclinePlanRequest
{
    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly BillingContacts $contacts,
        private readonly Tenancy $tenancy,
    ) {}

    public function handle(PlanChangeRequest $request, User $admin, string $note): PlanChangeRequest
    {
        if ($request->status !== PlanChangeRequest::PENDING) {
            throw ValidationException::withMessages(['note' => 'That request has already been settled.']);
        }

        $organization = Organization::query()->findOrFail($request->organization_id);

        return $this->tenancy->run($organization, function () use ($request, $admin, $note, $organization): PlanChangeRequest {
            $request->forceFill([
                'status' => PlanChangeRequest::DECLINED,
                'decided_by' => $admin->id,
                'decided_at' => now(),
                'decision_note' => $note,
            ])->save();

            $this->activity->log('platform.plan_request_declined', $request, [
                'to' => $request->to_plan->value,
                'note' => $note,
            ], actor: $admin, actorType: 'platform', organization: $organization);

            Notification::send($this->contacts->for($organization), new PlanNoticeNotification(
                "Your request to move to {$request->to_plan->label()} was not approved",
                $note,
                'warning',
            ));

            return $request;
        });
    }
}
