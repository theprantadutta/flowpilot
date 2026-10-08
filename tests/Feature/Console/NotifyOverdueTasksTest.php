<?php

use App\Enums\TaskStatus;
use App\Models\Organization;
use App\Models\Task;
use App\Notifications\TaskOverdueNotification;
use Illuminate\Support\Facades\Notification;

it('tells each assignee once about a task that has become overdue', function () {
    Notification::fake();
    $organization = Organization::factory()->create();
    $assignee = memberIn($organization);
    $late = Task::factory()->for($organization)->overdue()->create(['assignee_id' => $assignee->id]);
    Task::factory()->for($organization)->overdue()->status(TaskStatus::Done)->create(['assignee_id' => $assignee->id]);
    Task::factory()->for($organization)->create(['assignee_id' => $assignee->id, 'due_date' => now()->addDays(3)]);

    $this->artisan('tasks:notify-overdue')->assertSuccessful();
    $this->artisan('tasks:notify-overdue')->assertSuccessful();

    Notification::assertSentToTimes($assignee, TaskOverdueNotification::class, 1);
    Notification::assertSentTo($assignee, TaskOverdueNotification::class, fn ($notification) => $notification->taskId === $late->id
        && $notification->organizationId === $organization->id);
});

it('uses each organization’s own date to decide what is overdue', function () {
    Notification::fake();
    $this->travelTo(now('UTC')->setTime(20, 0));

    // At 20:00 UTC it is already tomorrow in Auckland, so a task due today is overdue there.
    // In Honolulu it is still this morning, so the same due date is not overdue yet.
    $auckland = Organization::factory()->create(['timezone' => 'Pacific/Auckland']);
    $honolulu = Organization::factory()->create(['timezone' => 'Pacific/Honolulu']);
    $todayUtc = now('UTC')->toDateString();

    $aucklandAssignee = memberIn($auckland);
    $honoluluAssignee = memberIn($honolulu);
    Task::factory()->for($auckland)->create(['assignee_id' => $aucklandAssignee->id, 'due_date' => $todayUtc]);
    Task::factory()->for($honolulu)->create(['assignee_id' => $honoluluAssignee->id, 'due_date' => $todayUtc]);

    $this->artisan('tasks:notify-overdue')->assertSuccessful();

    Notification::assertSentTo($aucklandAssignee, TaskOverdueNotification::class);
    Notification::assertNotSentTo($honoluluAssignee, TaskOverdueNotification::class);
});

it('starts counting again when the due date is moved', function () {
    $organization = Organization::factory()->create();
    $task = Task::factory()->for($organization)->overdue()->create(['assignee_id' => memberIn($organization)->id, 'overdue_notified_at' => now()]);

    \Pest\Laravel\actingAs($organization->owner)->patch(route('tasks.update', [$organization, $task]), ['due_date' => now()->subDay()->toDateString()]);

    expect($task->fresh()->overdue_notified_at)->toBeNull();
});
