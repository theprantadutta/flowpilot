<?php

namespace App\Actions\Billing;

use App\Enums\Plan;
use App\Models\PlanChangeRequest;
use App\Models\User;
use App\Notifications\PlanChangeRequestedNotification;
use App\Support\Activity\ActivityLogger;
use App\Support\Billing\Entitlements;
use App\Support\Tenancy\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * An owner asks to move to a higher plan. With no payment provider
 * connected, the FlowPilot team is told and settles it from platform
 * administration. One request is open at a time; a new one replaces it.
 */
class RequestPlanChange
{
    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly Entitlements $entitlements,
        private readonly Tenancy $tenancy,
    ) {}

    public function handle(User $requester, Plan $plan, ?string $message): PlanChangeRequest
    {
        $organization = $this->tenancy->currentOrFail();
        $current = $this->entitlements->plan();

        if ($plan->rank() <= $current->rank() && ! $this->entitlements->subscription()?->isOnTrial()) {
            throw ValidationException::withMessages(['plan' => "You are already on {$current->label()}. Choose a higher plan."]);
        }

        $request = DB::transaction(function () use ($requester, $plan, $message, $current): PlanChangeRequest {
            PlanChangeRequest::query()
                ->where('status', PlanChangeRequest::PENDING)
                ->update(['status' => PlanChangeRequest::WITHDRAWN, 'decided_at' => now()]);

            return PlanChangeRequest::query()->create([
                'requested_by' => $requester->id,
                'from_plan' => $current,
                'to_plan' => $plan,
                'message' => $message,
            ]);
        });

        $this->activity->log('billing.plan_change_requested', $request, [
            'from' => $current->value,
            'to' => $plan->value,
        ], actor: $requester);

        $platformAdmins = User::query()->where('is_platform_admin', true)->get();

        if ($platformAdmins->isNotEmpty()) {
            Notification::send($platformAdmins, new PlanChangeRequestedNotification($organization, $request, $requester));
        }

        return $request;
    }
}
