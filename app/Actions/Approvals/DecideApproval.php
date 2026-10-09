<?php

namespace App\Actions\Approvals;

use App\Enums\ApprovalStatus;
use App\Jobs\AdvanceWorkflowRun;
use App\Models\Approval;
use App\Models\User;
use App\Notifications\ApprovalDecidedNotification;
use App\Support\Activity\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Approve, reject or ask for changes. The row is locked while deciding, so
 * two people deciding at once (or a double click) cannot both win.
 */
class DecideApproval
{
    public const array DECISIONS = ['approve', 'reject', 'request_changes'];

    public function __construct(private readonly ActivityLogger $activity) {}

    /**
     * @param  'approve'|'reject'|'request_changes'  $decision
     *
     * @throws ValidationException When the request is no longer waiting for a decision.
     */
    public function handle(Approval $approval, User $actor, string $decision, ?string $note = null): Approval
    {
        $approval = DB::transaction(function () use ($approval, $actor, $decision, $note): Approval {
            /** @var Approval $locked */
            $locked = Approval::query()->whereKey($approval->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== ApprovalStatus::Pending) {
                throw ValidationException::withMessages([
                    'decision' => "This request was already {$locked->status->label()} and cannot be decided again.",
                ]);
            }

            $status = match ($decision) {
                'approve' => ApprovalStatus::Approved,
                'reject' => ApprovalStatus::Rejected,
                default => ApprovalStatus::ChangesRequested,
            };

            $locked->forceFill([
                'status' => $status,
                'decided_by' => $actor->id,
                'decided_at' => now(),
                'decision_note' => $note,
            ])->save();

            $this->activity->log(match ($status) {
                ApprovalStatus::Approved => 'approval.approved',
                ApprovalStatus::Rejected => 'approval.rejected',
                default => 'approval.changes_requested',
            }, $locked, [
                'title' => $locked->title,
                'reference' => $locked->reference(),
                'note' => $note,
                'on_behalf' => ! $locked->isWaitingOn($actor, null) && $locked->approver_id !== null ? $locked->approver?->name : null,
            ], actor: $actor);

            // A workflow waiting on the decision carries on once it is final.
            if ($locked->workflow_run_id !== null && $status !== ApprovalStatus::ChangesRequested) {
                AdvanceWorkflowRun::dispatch($locked->organization_id, $locked->workflow_run_id)->afterCommit();
            }

            return $locked;
        });

        if ($approval->requester_id !== null && $approval->requester_id !== $actor->id) {
            $approval->requester?->notify(new ApprovalDecidedNotification($approval, $actor->name));
        }

        return $approval;
    }
}
