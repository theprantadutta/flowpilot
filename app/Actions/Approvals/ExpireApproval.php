<?php

namespace App\Actions\Approvals;

use App\Enums\ApprovalStatus;
use App\Jobs\AdvanceWorkflowRun;
use App\Models\Approval;
use App\Notifications\ApprovalDecidedNotification;
use App\Support\Activity\ActivityLogger;
use Illuminate\Support\Facades\DB;

/**
 * Nobody decided in time and the request was set to be rejected when overdue.
 */
class ExpireApproval
{
    public function __construct(private readonly ActivityLogger $activity) {}

    public function handle(Approval $approval): ?Approval
    {
        $expired = DB::transaction(function () use ($approval): ?Approval {
            /** @var Approval $locked */
            $locked = Approval::query()->whereKey($approval->id)->lockForUpdate()->firstOrFail();

            // Decided in the meantime: nothing to do.
            if ($locked->status !== ApprovalStatus::Pending) {
                return null;
            }

            $locked->forceFill(['status' => ApprovalStatus::Expired, 'decided_at' => now()])->save();

            $this->activity->log('approval.expired', $locked, [
                'title' => $locked->title,
                'reference' => $locked->reference(),
            ], actorType: 'system');

            if ($locked->workflow_run_id !== null) {
                AdvanceWorkflowRun::dispatch($locked->organization_id, $locked->workflow_run_id)->afterCommit();
            }

            return $locked;
        });

        $expired?->requester?->notify(new ApprovalDecidedNotification($expired, null));

        return $expired;
    }
}
