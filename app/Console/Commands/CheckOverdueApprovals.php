<?php

namespace App\Console\Commands;

use App\Actions\Approvals\ExpireApproval;
use App\Enums\ApprovalStatus;
use App\Models\Approval;
use App\Models\Organization;
use App\Notifications\ApprovalRequiredNotification;
use App\Support\Approvals\Approvers;
use App\Support\Tenancy\Tenancy;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Approvals past their due date are either expired (when set to reject if
 * overdue) or the approvers get one reminder. Crosses organizations to find
 * them, then handles each inside its own organization.
 */
#[Signature('approvals:check-overdue')]
#[Description('Expire or send reminders for approvals that are past their due date')]
class CheckOverdueApprovals extends Command
{
    public function handle(Tenancy $tenancy, ExpireApproval $expire, Approvers $approvers): int
    {
        $overdue = Approval::withoutOrganizationScope()
            ->where('status', ApprovalStatus::Pending->value)
            ->whereNotNull('due_at')
            ->where('due_at', '<=', now())
            ->where(fn ($query) => $query
                ->where('when_overdue', Approval::WHEN_OVERDUE_REJECT)
                ->orWhereNull('reminded_at'))
            ->with(['requester:id,name', 'approver:id,name'])
            ->orderBy('due_at')
            ->limit(500)
            ->get()
            ->groupBy('organization_id');

        $expired = 0;
        $reminded = 0;

        foreach ($overdue as $organizationId => $approvals) {
            $organization = Organization::query()->find($organizationId);

            if ($organization === null || ! $organization->isActive()) {
                continue;
            }

            $tenancy->run($organization, function () use ($approvals, $expire, $approvers, &$expired, &$reminded): void {
                foreach ($approvals as $approval) {
                    if ($approval->when_overdue === Approval::WHEN_OVERDUE_REJECT) {
                        $expired += $expire->handle($approval) ? 1 : 0;

                        continue;
                    }

                    $recipients = $approvers->for($approval);

                    if ($recipients->isNotEmpty()) {
                        Notification::send($recipients, new ApprovalRequiredNotification($approval, reminder: true));
                    }

                    $approval->forceFill(['reminded_at' => now()])->save();
                    $reminded++;
                }
            });
        }

        $this->components->info("Expired {$expired} and sent reminders for {$reminded} overdue approvals.");

        return self::SUCCESS;
    }
}
