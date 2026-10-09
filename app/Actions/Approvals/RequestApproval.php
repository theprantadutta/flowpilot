<?php

namespace App\Actions\Approvals;

use App\Enums\ApprovalStatus;
use App\Models\Approval;
use App\Models\User;
use App\Models\WorkflowRun;
use App\Notifications\ApprovalRequiredNotification;
use App\Support\Activity\ActivityLogger;
use App\Support\Approvals\Approvers;
use App\Support\Tenancy\OrganizationSequence;
use App\Support\Tenancy\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class RequestApproval
{
    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly OrganizationSequence $sequence,
        private readonly Approvers $approvers,
        private readonly Tenancy $tenancy,
    ) {}

    /**
     * Raise an approval request and tell the people who can decide it.
     *
     * @param  array<string, mixed>  $attributes  Validated attributes: title, description, approver_id or approver_role, amount, currency, priority, due_at, details…
     * @param  WorkflowRun|null  $automation  The run raising the request, when a workflow step does.
     */
    public function handle(?User $requester, array $attributes, ?WorkflowRun $automation = null): Approval
    {
        $organization = $this->tenancy->currentOrFail();

        $approval = DB::transaction(function () use ($requester, $attributes, $automation, $organization): Approval {
            $approval = Approval::query()->create([
                ...$attributes,
                'number' => $this->sequence->next('approvals'),
                'status' => ApprovalStatus::Pending,
                'requester_id' => $requester?->id,
                'currency' => isset($attributes['amount']) ? ($attributes['currency'] ?? $organization->currency) : null,
                'due_at' => $attributes['due_at'] ?? now()->addHours((int) $organization->setting('workflows.approval_due_hours', 48)),
            ]);

            $this->activity->log('approval.requested', $approval, [
                'title' => $approval->title,
                'reference' => $approval->reference(),
                'approver' => $this->approvers->describe($approval),
                ...ActivityLogger::automation($automation),
            ], actor: $requester, actorType: $automation && ! $requester ? 'workflow' : 'user');

            return $approval;
        });

        $recipients = $this->approvers->for($approval);

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new ApprovalRequiredNotification($approval));
        }

        return $approval;
    }
}
