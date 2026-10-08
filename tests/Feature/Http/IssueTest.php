<?php

use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Enums\Role;
use App\Models\Issue;
use App\Models\Organization;
use App\Notifications\IssueAssignedNotification;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

it('reports an issue, numbers it and tells the assignee', function () {
    Notification::fake();
    $organization = Organization::factory()->create();
    $assignee = memberIn($organization, Role::Operations);

    actingAs(memberIn($organization))
        ->post(route('issues.store', $organization), [
            'title' => 'Conveyor 2 stops under load',
            'severity' => 'critical',
            'assignee_id' => $assignee->id,
        ])
        ->assertRedirect();

    $issue = inTenant($organization, fn () => Issue::query()->sole());
    expect($issue)->number->toBe(1)->severity->toBe(IssueSeverity::Critical)->status->toBe(IssueStatus::Open);

    Notification::assertSentTo($assignee, IssueAssignedNotification::class, fn ($notification) => $notification->tone() === 'danger');
});

it('requires a severity', function () {
    $organization = Organization::factory()->create();

    actingAs($organization->owner)
        ->post(route('issues.store', $organization), ['title' => 'Something broke'])
        ->assertSessionHasErrors(['severity' => 'Choose how severe the issue is.']);
});

it('records when an issue is resolved and clears it when reopened', function () {
    $organization = Organization::factory()->create();
    $issue = Issue::factory()->for($organization)->create();

    actingAs($organization->owner)->patch(route('issues.update', [$organization, $issue]), ['status' => 'resolved']);
    expect($issue->fresh()->resolved_at)->not->toBeNull();

    actingAs($organization->owner)->patch(route('issues.update', [$organization, $issue]), ['status' => 'open']);
    expect($issue->fresh()->resolved_at)->toBeNull();
});

it('shows open issues most severe first by default', function () {
    $organization = Organization::factory()->create();
    Issue::factory()->for($organization)->severity(IssueSeverity::Low)->create(['title' => 'Minor']);
    Issue::factory()->for($organization)->severity(IssueSeverity::Critical)->create(['title' => 'Line down']);
    Issue::factory()->for($organization)->create(['title' => 'Already fixed', 'status' => IssueStatus::Resolved]);

    actingAs($organization->owner)
        ->get(route('issues.index', $organization))
        ->assertInertia(fn (Assert $page) => $page
            ->component('issues/Index')
            ->has('issues.data', 2)
            ->where('issues.data.0.title', 'Line down')
            ->where('openBySeverity.critical', 1));
});

it('returns 404 for an issue in another organization', function () {
    $organization = Organization::factory()->create();
    $foreign = Issue::factory()->create();

    actingAs($organization->owner)->get(route('issues.show', [$organization, $foreign]))->assertNotFound();
    actingAs($organization->owner)->delete(route('issues.destroy', [$organization, $foreign]))->assertNotFound();
});
