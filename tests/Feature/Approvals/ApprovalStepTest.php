<?php

use App\Enums\ApprovalStatus;
use App\Enums\Role;
use App\Enums\WorkflowRunStatus;
use App\Models\Approval;
use App\Models\Organization;
use App\Models\Workflow;
use App\Models\WorkflowRun;
use App\Notifications\ApprovalRequiredNotification;
use App\Workflows\Definition\DefinitionValidator;
use App\Workflows\Definition\WorkflowDefinition;
use App\Workflows\Engine\WorkflowEngine;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
use function Pest\Laravel\travel;

/**
 * Purchase request: anything over 5,000 needs finance after the manager.
 */
function purchaseWorkflow(Organization $organization, array $managerApproval = []): Workflow
{
    return publishedWorkflow($organization, [
        step('trigger', 'trigger', ['inputs' => [
            ['key' => 'item', 'label' => 'Item', 'type' => 'text', 'required' => true],
            ['key' => 'amount', 'label' => 'Amount', 'type' => 'money', 'required' => true],
        ]]),
        step('manager', 'approval', [
            'title' => 'Approve {{ input.item }}',
            'approver' => ['type' => 'role', 'role' => 'manager'],
            'amount_field' => 'input.amount',
            'priority' => 'high',
            ...$managerApproval,
        ]),
        step('large', 'condition', ['match' => 'all', 'rules' => [['field' => 'input.amount', 'operator' => 'greater_than', 'value' => '5000']]]),
        step('finance', 'approval', [
            'title' => 'Finance check: {{ input.item }}',
            'approver' => ['type' => 'role', 'role' => 'finance'],
            'amount_field' => 'input.amount',
        ]),
        step('approved', 'end', ['summary' => 'Approved by {{ steps.manager.decided_by }}']),
        step('rejected', 'end', ['summary' => 'Rejected']),
    ], [
        path('trigger', 'manager'),
        path('manager', 'large', 'approved'),
        path('manager', 'rejected', 'rejected'),
        path('large', 'finance', 'true'),
        path('large', 'approved', 'false'),
        path('finance', 'approved', 'approved'),
        path('finance', 'rejected', 'rejected'),
    ], name: 'Purchase approval');
}

function startPurchase(Workflow $workflow, $requester, array $input): WorkflowRun
{
    return inTenant($workflow->organization, fn () => app(WorkflowEngine::class)->start($workflow, input: $input, actor: $requester)->refresh());
}

it('waits for each approval and follows the decisions through to the end', function () {
    Notification::fake();
    $organization = Organization::factory()->create(['currency' => 'USD']);
    $employee = memberIn($organization, Role::Employee);
    $manager = memberIn($organization, Role::Manager);
    $finance = memberIn($organization, Role::Finance);

    $run = startPurchase(purchaseWorkflow($organization), $employee, ['item' => 'Hydraulic press seals', 'amount' => 620000]);

    $managerApproval = inTenant($organization, fn () => Approval::query()->sole());

    expect($run->status)->toBe(WorkflowRunStatus::Waiting)
        ->and($managerApproval->title)->toBe('Approve Hydraulic press seals')
        ->and($managerApproval->amount)->toBe(620000)
        ->and($managerApproval->requester_id)->toBe($employee->id)
        ->and($managerApproval->approver_role)->toBe(Role::Manager)
        ->and($managerApproval->workflow_run_id)->toBe($run->id)
        ->and(collect($managerApproval->details)->pluck('value', 'label')->all())->toMatchArray(['Item' => 'Hydraulic press seals', 'Amount' => '$6,200.00']);

    Notification::assertSentTo($manager, ApprovalRequiredNotification::class);

    actingAs($manager)->post(route('approvals.decide', [$organization, $managerApproval]), ['decision' => 'approve']);

    $financeApproval = inTenant($organization, fn () => Approval::query()->where('approver_role', 'finance')->sole());
    expect($run->refresh()->status)->toBe(WorkflowRunStatus::Waiting);

    actingAs($finance)->post(route('approvals.decide', [$organization, $financeApproval]), ['decision' => 'approve']);

    $run->refresh();

    expect($run->status)->toBe(WorkflowRunStatus::Completed)
        ->and($run->context['steps']['approved']['summary'])->toBe("Approved by {$manager->name}")
        ->and(inTenant($organization, fn () => $run->steps()->pluck('outcome', 'node_id')->all()))->toMatchArray([
            'manager' => 'approved',
            'large' => 'true',
            'finance' => 'approved',
        ]);
});

it('keeps waiting while changes are requested, and goes down rejected when refused', function () {
    $organization = Organization::factory()->create();
    $employee = memberIn($organization, Role::Employee);
    $manager = memberIn($organization, Role::Manager);

    $run = startPurchase(purchaseWorkflow($organization), $employee, ['item' => 'Gloves', 'amount' => 20000]);
    $approval = inTenant($organization, fn () => Approval::query()->sole());

    actingAs($manager)->post(route('approvals.decide', [$organization, $approval]), ['decision' => 'request_changes', 'note' => 'Which size?']);
    expect($run->refresh()->status)->toBe(WorkflowRunStatus::Waiting);

    actingAs($employee)->post(route('approvals.resubmit', [$organization, $approval]), ['note' => 'Size L']);
    actingAs($manager)->post(route('approvals.decide', [$organization, $approval]), ['decision' => 'reject', 'note' => 'We have stock.']);

    expect($run->refresh()->status)->toBe(WorkflowRunStatus::Completed)
        ->and($run->context['steps']['rejected']['summary'])->toBe('Rejected')
        ->and($run->context['steps']['manager']['note'])->toBe('We have stock.');
});

it('treats an expired request as rejected', function () {
    $organization = Organization::factory()->create();
    memberIn($organization, Role::Manager);

    $run = startPurchase(
        purchaseWorkflow($organization, ['due_in_hours' => 4, 'when_overdue' => 'reject']),
        memberIn($organization, Role::Employee),
        ['item' => 'Pallets', 'amount' => 30000],
    );

    travel(5)->hours();
    artisan('approvals:check-overdue');

    expect($run->refresh()->status)->toBe(WorkflowRunStatus::Completed)
        ->and(inTenant($organization, fn () => Approval::query()->sole()->status))->toBe(ApprovalStatus::Expired)
        ->and(inTenant($organization, fn () => $run->steps()->where('node_id', 'manager')->value('outcome')))->toBe('rejected');
});

it('withdraws the open request when the run is cancelled', function () {
    $organization = Organization::factory()->create();
    memberIn($organization, Role::Manager);

    $run = startPurchase(purchaseWorkflow($organization), $organization->owner, ['item' => 'Desk', 'amount' => 10000]);

    actingAs($organization->owner)->post(route('workflow-runs.cancel', [$organization, $run]));

    expect(inTenant($organization, fn () => Approval::query()->sole()->status))->toBe(ApprovalStatus::Cancelled);
});

it('validates who approves and where the amount comes from', function () {
    $organization = Organization::factory()->create();

    $problems = inTenant($organization, fn () => array_column(app(DefinitionValidator::class)->validate(
        WorkflowDefinition::fromArray([
            'nodes' => [
                step('trigger', 'trigger', ['inputs' => [['key' => 'item', 'label' => 'Item', 'type' => 'text']]]),
                step('ask', 'approval', ['title' => '', 'approver' => ['type' => 'assignee'], 'amount_field' => 'input.item', 'due_in_hours' => 1000]),
            ],
            'edges' => [path('trigger', 'ask')],
        ]),
        'manual',
        $organization,
    ), 'message'));

    expect($problems)->toContain(
        'Write what is being approved.',
        'Choose a person, a role or a person field to approve.',
        'The amount must come from an amount field.',
        'The decision can be due between 1 hour and 30 days after asking.',
    );
});
