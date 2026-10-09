<?php

namespace App\Actions\Approvals;

use App\Enums\ApprovalStatus;
use App\Models\Approval;
use App\Models\User;
use App\Support\Activity\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WithdrawApproval
{
    public function __construct(private readonly ActivityLogger $activity) {}

    public function handle(Approval $approval, User $actor): Approval
    {
        return DB::transaction(function () use ($approval, $actor): Approval {
            /** @var Approval $locked */
            $locked = Approval::query()->whereKey($approval->id)->lockForUpdate()->firstOrFail();

            if (! $locked->status->isOpen()) {
                throw ValidationException::withMessages(['approval' => 'This request has already been decided.']);
            }

            $locked->forceFill(['status' => ApprovalStatus::Cancelled, 'decided_at' => now()])->save();

            $this->activity->log('approval.withdrawn', $locked, [
                'title' => $locked->title,
                'reference' => $locked->reference(),
            ], actor: $actor);

            return $locked;
        });
    }
}
