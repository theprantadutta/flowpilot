<?php

namespace App\Console\Commands;

use App\Enums\OrganizationStatus;
use App\Models\Organization;
use App\Models\Task;
use App\Notifications\TaskOverdueNotification;
use App\Support\Tenancy\Tenancy;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tasks:notify-overdue')]
#[Description('Tell assignees about tasks that have just become overdue, once per due date')]
class NotifyOverdueTasks extends Command
{
    public function handle(Tenancy $tenancy): int
    {
        $notified = 0;

        Organization::query()
            ->where('status', OrganizationStatus::Active)
            ->lazyById(100)
            ->each(function (Organization $organization) use ($tenancy, &$notified): void {
                $tenancy->run($organization, function (Organization $organization) use (&$notified): void {
                    // "Overdue" starts when the due date has passed where the organization is.
                    $today = now($organization->timezone)->toDateString();

                    Task::query()
                        ->overdue($today)
                        ->whereNotNull('assignee_id')
                        ->whereNull('overdue_notified_at')
                        ->with('assignee')
                        ->lazyById(200)
                        ->each(function (Task $task) use (&$notified): void {
                            $task->assignee?->notify(new TaskOverdueNotification($task));
                            $task->forceFill(['overdue_notified_at' => now()])->saveQuietly();
                            $notified++;
                        });
                });
            });

        $this->components->info("Notified {$notified} overdue ".str('task')->plural($notified).'.');

        return self::SUCCESS;
    }
}
