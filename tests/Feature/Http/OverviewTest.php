<?php

use App\Enums\IssueSeverity;
use App\Enums\PurchaseRequestStatus;
use App\Enums\Role;
use App\Enums\TaskStatus;
use App\Models\Approval;
use App\Models\InventoryItem;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\PurchaseRequest;
use App\Models\Task;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

it('puts what needs attention first, with the numbers behind it', function () {
    $organization = Organization::factory()->create();
    $owner = $organization->owner;

    inTenant($organization, function () use ($organization, $owner) {
        Task::factory()->overdue()->create(['organization_id' => $organization->id, 'assignee_id' => $owner->id]);
        Task::factory()->create(['organization_id' => $organization->id, 'assignee_id' => $owner->id, 'due_date' => now()->toDateString()]);
        Issue::factory()->severity(IssueSeverity::Critical)->create(['organization_id' => $organization->id]);
        Approval::factory()->create(['organization_id' => $organization->id, 'approver_id' => $owner->id, 'approver_role' => null, 'created_at' => now()->subHours(5)]);
        InventoryItem::factory()->create(['organization_id' => $organization->id, 'current_stock' => 0, 'reorder_point' => 5]);
        PurchaseRequest::factory()->create(['organization_id' => $organization->id, 'status' => PurchaseRequestStatus::Submitted]);
    });

    actingAs($owner)
        ->get(route('overview', $organization))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Overview')
            ->where('stats.1.key', 'due_today')
            ->where('stats.1.value', 1)
            ->where('stats.2.key', 'overdue')
            ->where('stats.2.value', 1)
            ->missing('attention')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('attention', fn ($items) => collect($items)->pluck('key')->all() === ['overdue_tasks', 'critical_issues', 'approvals', 'stock', 'purchases'])
                ->where('attention.2.description', 'The oldest has waited 5 hours.')
                ->has('myWork', 2)
                ->where('myWork.0.is_overdue', true)
                ->has('trends', 2)));
});

it('leaves out what the member may not act on', function () {
    $organization = Organization::factory()->create();
    $employee = memberIn($organization, Role::Employee);

    inTenant($organization, fn () => PurchaseRequest::factory()->create(['organization_id' => $organization->id, 'status' => PurchaseRequestStatus::Submitted]));

    actingAs($employee)
        ->get(route('overview', $organization))
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('attention', fn ($items) => ! collect($items)->contains('key', 'purchases'))));
});

it('is calm when nothing needs attention', function () {
    $organization = Organization::factory()->create();
    inTenant($organization, fn () => Task::factory()->status(TaskStatus::Done)->create(['organization_id' => $organization->id]));

    actingAs($organization->owner)
        ->get(route('overview', $organization))
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload->where('attention', [])->where('myWork', [])));
});

it('does not ask members to decide their own purchase requests', function () {
    $organization = Organization::factory()->create();

    inTenant($organization, fn () => PurchaseRequest::factory()->create([
        'organization_id' => $organization->id,
        'status' => PurchaseRequestStatus::Submitted,
        'requester_id' => $organization->owner_id,
    ]));

    actingAs($organization->owner)
        ->get(route('overview', $organization))
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('attention', fn ($items) => ! collect($items)->contains('key', 'purchases'))));
});
