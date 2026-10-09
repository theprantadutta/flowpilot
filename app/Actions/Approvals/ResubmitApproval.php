<?php

namespace App\Actions\Approvals;

use App\Enums\ApprovalStatus;
use App\Models\Approval;
use App\Models\User;
use App\Notifications\ApprovalRequiredNotification;
use App\Support\Activity\ActivityLogger;
use App\Support\Approvals\Approvers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * The requester answers a request for changes and sends it back for a decision.
 */
class ResubmitApproval
{
    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly Approvers $approvers,
    ) {}

    /**
     * @param  array{title?: string, description?: string|null, amount?: int|null}  $changes
     */
    public function handle(Approval $approval, User $actor, array $changes, ?string $note): Approval
    {
        $approval = DB::transaction(function () use ($approval, $actor, $changes, $note): Approval {
            /** @var Approval $locked */
            $locked = Approval::query()->whereKey($approval->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== ApprovalStatus::ChangesRequested) {
                throw ValidationException::withMessages(['note' => 'Only requests sent back for changes can be resubmitted.']);
            }

            $before = ['title' => $locked->title, 'description' => $locked->description, 'amount' => $locked->amount];

            $locked->fill($changes)->forceFill([
                'status' => ApprovalStatus::Pending,
                'decided_by' => null,
                'decided_at' => null,
                'decision_note' => null,
                'reminded_at' => null,
            ])->save();

            $this->activity->log('approval.resubmitted', $locked, [
                'title' => $locked->title,
                'reference' => $locked->reference(),
                'note' => $note,
                'changes' => ActivityLogger::diff($before, ['title' => $locked->title, 'description' => $locked->description, 'amount' => $locked->amount]),
            ], actor: $actor);

            return $locked;
        });

        $recipients = $this->approvers->for($approval);

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new ApprovalRequiredNotification($approval));
        }

        return $approval;
    }
}
