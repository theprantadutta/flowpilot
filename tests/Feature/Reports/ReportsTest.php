<?php

use App\Enums\ApprovalStatus;
use App\Enums\MovementType;
use App\Enums\ProjectStatus;
use App\Enums\ReportType;
use App\Enums\Role;
use App\Enums\StepRunStatus;
use App\Enums\TaskStatus;
use App\Enums\WorkflowRunStatus;
use App\Models\ActivityLog;
use App\Models\Approval;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\Workflow;
use App\Models\WorkflowRun;
use App\Models\WorkflowStepRun;
use App\Support\Reports\ReportQuery;
use App\Support\Reports\ReportRegistry;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

beforeEach(function () {
    travelTo(CarbonImmutable::parse('2026-10-09 12:00:00', 'UTC'));
});

/**
 * Build a report for an organization the way the page does.
 *
 * @param  array<string, string>  $filters
 * @return array{result: array<string, mixed>, rows: list<array<string, mixed>>}
 */
function runReport(Organization $organization, ReportType $type, string $range = 'last_30_days', ?string $group = null, array $filters = []): array
{
    return inTenant($organization, function () use ($organization, $type, $range, $group, $filters): array {
        $report = app(ReportRegistry::class)->get($type);
        $query = ReportQuery::make($organization->timezone, $range, group: $group ?? $report->defaultGroup(), filters: $filters);

        return ['result' => $report->summarize($query)->toArray(), 'rows' => array_values([...$report->rows($query)])];
    });
}

/**
 * @param  array<string, mixed>  $result
 */
function tile(array $result, string $key): mixed
{
    return collect($result['tiles'])->firstWhere('key', $key)['value'] ?? null;
}

describe('access', function () {
    it('lists only the reports a member may open', function () {
        $organization = Organization::factory()->create();

        actingAs($organization->owner)
            ->get(route('reports.index', $organization))
            ->assertInertia(fn (Assert $page) => $page
                ->component('reports/Index')
                ->has('reports', count(ReportType::cases()))
                ->where('can.export', true));

        actingAs(memberIn($organization, Role::Procurement))
            ->get(route('reports.index', $organization))
            ->assertInertia(fn (Assert $page) => $page->where('can.export', false));
    });

    it('keeps reports from members without the reports permission', function () {
        $organization = Organization::factory()->create();
        $employee = memberIn($organization, Role::Employee);

        actingAs($employee)->get(route('reports.index', $organization))->assertForbidden();
        actingAs($employee)->get(route('reports.show', [$organization, 'task-completion']))->assertForbidden();
    });

    it('answers 404 for a report that does not exist', function () {
        $organization = Organization::factory()->create();

        actingAs($organization->owner)->get(route('reports.show', [$organization, 'payroll']))->assertNotFound();
    });

    it('shows a report with its figures, charts and table', function () {
        $organization = Organization::factory()->create();
        inTenant($organization, fn () => Task::factory()->count(2)->status(TaskStatus::Done)->create(['organization_id' => $organization->id, 'assignee_id' => $organization->owner_id]));

        actingAs($organization->owner)
            ->get(route('reports.show', [$organization, 'task-completion', 'range' => 'last_7_days', 'group' => 'project']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('reports/Show')
                ->where('parameters.range', 'last_7_days')
                ->where('parameters.group', 'project')
                ->where('parameters.from', '2026-10-03')
                ->where('parameters.to', '2026-10-09')
                ->missing('result')
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->where('result.tiles.1.key', 'completed')
                    ->where('result.tiles.1.value', 2)
                    ->has('result.charts.0.series', 2)
                    ->has('result.charts.0.labels', 7)
                    ->where('table.rows.0.name', 'No project')
                    ->where('table.rows.0.completed', 2)));
    });

    it('drops filter values that do not belong to the organization', function () {
        $organization = Organization::factory()->create();
        $other = Organization::factory()->create();
        $theirs = inTenant($other, fn () => Project::factory()->create(['organization_id' => $other->id]));

        actingAs($organization->owner)
            ->get(route('reports.show', [$organization, 'task-completion', 'project' => $theirs->id, 'range' => 'nonsense']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('parameters.range', 'last_30_days')
                ->where('parameters.filters', []));
    });
});

describe('task completion', function () {
    it('counts created and completed work, on-time share and cycle time', function () {
        $organization = Organization::factory()->create();
        $other = Organization::factory()->create();
        $assignee = memberIn($organization, Role::Employee, ['name' => 'Priya Nair']);

        inTenant($organization, function () use ($organization, $assignee) {
            // Finished on time: created Oct 1, due Oct 5, done Oct 4.
            Task::factory()->create(['organization_id' => $organization->id, 'assignee_id' => $assignee->id, 'status' => TaskStatus::Done, 'created_at' => '2026-10-01 09:00:00', 'completed_at' => '2026-10-04 09:00:00', 'due_date' => '2026-10-05']);
            // Finished late: created Oct 2, due Oct 3, done Oct 6.
            Task::factory()->create(['organization_id' => $organization->id, 'assignee_id' => $assignee->id, 'status' => TaskStatus::Done, 'created_at' => '2026-10-02 09:00:00', 'completed_at' => '2026-10-06 09:00:00', 'due_date' => '2026-10-03']);
            // Still open and overdue.
            Task::factory()->create(['organization_id' => $organization->id, 'assignee_id' => $assignee->id, 'created_at' => '2026-10-03 09:00:00', 'due_date' => '2026-10-07']);
        });
        inTenant($other, fn () => Task::factory()->count(5)->status(TaskStatus::Done)->create(['organization_id' => $other->id]));

        ['result' => $result, 'rows' => $rows] = runReport($organization, ReportType::TaskCompletion);

        expect(tile($result, 'created'))->toBe(3)
            ->and(tile($result, 'completed'))->toBe(2)
            ->and(tile($result, 'on_time'))->toBe(50.0)
            ->and(tile($result, 'cycle'))->toBe(3.5)
            ->and(tile($result, 'overdue'))->toBe(1)
            ->and($rows)->toHaveCount(1)
            ->and($rows[0])->toMatchArray(['name' => 'Priya Nair', 'created' => 3, 'completed' => 2, 'on_time' => 50.0, 'average_days' => 3.5, 'open' => 1, 'overdue' => 1]);
    });

    it('buckets by the organization\'s calendar, not UTC', function () {
        $organization = Organization::factory()->create(['timezone' => 'Asia/Dhaka']);

        // 20:00 UTC on Oct 5 is 02:00 on Oct 6 in Dhaka.
        inTenant($organization, fn () => Task::factory()->create(['organization_id' => $organization->id, 'status' => TaskStatus::Done, 'created_at' => '2026-10-05 20:00:00', 'completed_at' => '2026-10-05 20:00:00']));

        ['result' => $result] = runReport($organization, ReportType::TaskCompletion, 'last_7_days');
        $chart = $result['charts'][0];
        $completed = array_combine($chart['labels'], $chart['series'][1]['values']);

        expect($completed['Oct 6'])->toBe(1)
            ->and($completed['Oct 5'])->toBe(0);
    });
});

describe('approval turnaround', function () {
    it('measures hours to decision and the approval rate', function () {
        $organization = Organization::factory()->create();
        $approver = memberIn($organization, Role::Finance, ['name' => 'Dana Reyes']);

        inTenant($organization, function () use ($organization, $approver) {
            Approval::factory()->create(['organization_id' => $organization->id, 'status' => ApprovalStatus::Approved, 'decided_by' => $approver->id, 'created_at' => '2026-10-05 08:00:00', 'decided_at' => '2026-10-05 12:00:00']);
            Approval::factory()->create(['organization_id' => $organization->id, 'status' => ApprovalStatus::Rejected, 'decided_by' => $approver->id, 'created_at' => '2026-10-06 08:00:00', 'decided_at' => '2026-10-06 16:00:00']);
            Approval::factory()->create(['organization_id' => $organization->id, 'status' => ApprovalStatus::Pending, 'approver_id' => $approver->id, 'created_at' => '2026-10-08 08:00:00']);
        });

        ['result' => $result, 'rows' => $rows] = runReport($organization, ReportType::ApprovalTurnaround);

        expect(tile($result, 'decided'))->toBe(2)
            ->and(tile($result, 'turnaround'))->toBe(6.0)
            ->and(tile($result, 'approved'))->toBe(50.0)
            ->and(tile($result, 'waiting'))->toBe(1)
            ->and($rows[0])->toMatchArray(['name' => 'Dana Reyes', 'decided' => 2, 'approved' => 1, 'rejected' => 1, 'average_hours' => 6.0, 'longest_hours' => 8.0, 'waiting' => 1]);
    });
});

describe('workflows', function () {
    it('reports runs by outcome for each workflow', function () {
        $organization = Organization::factory()->create();
        $workflow = inTenant($organization, fn () => Workflow::factory()->published()->create(['organization_id' => $organization->id, 'name' => 'Purchase approval']));

        inTenant($organization, function () use ($workflow) {
            WorkflowRun::factory()->count(3)->create(['workflow_id' => $workflow->id, 'status' => WorkflowRunStatus::Completed, 'created_at' => '2026-10-07 09:00:00', 'started_at' => '2026-10-07 09:00:00', 'completed_at' => '2026-10-07 09:02:00']);
            WorkflowRun::factory()->status(WorkflowRunStatus::Failed)->create(['workflow_id' => $workflow->id, 'created_at' => '2026-10-08 09:00:00']);
        });

        ['result' => $result, 'rows' => $rows] = runReport($organization, ReportType::WorkflowExecution);

        expect(tile($result, 'runs'))->toBe(4)
            ->and(tile($result, 'success'))->toBe(75.0)
            ->and(tile($result, 'failed'))->toBe(1)
            ->and(tile($result, 'duration'))->toBe(120.0)
            ->and($rows[0]['name']['label'])->toBe('Purchase approval')
            ->and($rows[0])->toMatchArray(['runs' => 4, 'completed' => 3, 'failed' => 1, 'success_rate' => 75.0, 'average_duration' => 120]);
    });

    it('lists failed runs with the step they stopped at', function () {
        $organization = Organization::factory()->create();

        inTenant($organization, function () use ($organization) {
            $run = WorkflowRun::factory()->status(WorkflowRunStatus::Failed)->create(['organization_id' => $organization->id, 'subject_label' => 'PR-4']);
            WorkflowStepRun::query()->create([
                'workflow_run_id' => $run->id, 'sequence' => 2, 'node_id' => 'notify', 'node_type' => 'webhook',
                'label' => 'Tell the ERP', 'status' => StepRunStatus::Failed, 'error' => 'The receiving system answered 500.',
            ]);
        });

        ['result' => $result, 'rows' => $rows] = runReport($organization, ReportType::WorkflowFailures);
        ['rows' => $byStep] = runReport($organization, ReportType::WorkflowFailures, group: 'step');

        expect(tile($result, 'failed'))->toBe(1)
            ->and(tile($result, 'step'))->toBe('Webhook steps')
            ->and($rows[0])->toMatchArray(['step' => 'Tell the ERP', 'error' => 'The receiving system answered 500.', 'subject' => 'PR-4'])
            ->and($byStep[0])->toMatchArray(['name' => 'Webhook', 'failed' => 1, 'last_error' => 'The receiving system answered 500.']);
    });
});

describe('inventory status', function () {
    it('values stock and counts what is low, by category', function () {
        $organization = Organization::factory()->create();

        inTenant($organization, function () use ($organization) {
            $safety = InventoryCategory::query()->create(['name' => 'Safety']);
            $location = InventoryLocation::factory()->create(['organization_id' => $organization->id]);
            $gloves = InventoryItem::factory()->create(['organization_id' => $organization->id, 'category_id' => $safety->id, 'current_stock' => 10, 'reorder_point' => 20, 'unit_cost_amount' => 1250, 'currency' => 'USD']);
            InventoryItem::factory()->create(['organization_id' => $organization->id, 'current_stock' => 0, 'reorder_point' => 5, 'unit_cost_amount' => 500, 'currency' => 'USD']);
            InventoryMovement::query()->create([
                'number' => 1, 'inventory_item_id' => $gloves->id, 'type' => MovementType::Receipt, 'quantity' => 10,
                'to_location_id' => $location->id, 'stock_after' => 10, 'occurred_at' => '2026-10-06 10:00:00',
            ]);
        });

        ['result' => $result, 'rows' => $rows] = runReport($organization, ReportType::InventoryStatus);
        ['rows' => $byCategory] = runReport($organization, ReportType::InventoryStatus, group: 'category');

        expect(tile($result, 'items'))->toBe(2)
            ->and(tile($result, 'low'))->toBe(1)
            ->and(tile($result, 'out'))->toBe(1)
            ->and(tile($result, 'value'))->toBe(12500)
            ->and(tile($result, 'movements'))->toBe(1)
            ->and($rows[0]['status']['value'])->toBe('out')
            ->and($rows[1])->toMatchArray(['on_hand' => 10, 'received' => 10, 'value' => ['amount' => 12500, 'currency' => 'USD']])
            ->and(collect($byCategory)->firstWhere('name', 'Safety'))->toMatchArray(['items' => 1, 'low' => 1, 'value' => ['amount' => 12500, 'currency' => 'USD']]);
    });
});

describe('project progress', function () {
    it('shows progress and late projects', function () {
        $organization = Organization::factory()->create();

        inTenant($organization, function () use ($organization) {
            $late = Project::factory()->status(ProjectStatus::Active)->create(['organization_id' => $organization->id, 'name' => 'Line 2 retrofit', 'due_date' => '2026-10-01']);
            Task::factory()->status(TaskStatus::Done)->create(['organization_id' => $organization->id, 'project_id' => $late->id]);
            Task::factory()->create(['organization_id' => $organization->id, 'project_id' => $late->id, 'due_date' => '2026-10-02']);
            Project::factory()->status(ProjectStatus::Completed)->create(['organization_id' => $organization->id]);
        });

        ['result' => $result, 'rows' => $rows] = runReport($organization, ReportType::ProjectProgress);

        expect(tile($result, 'active'))->toBe(1)
            ->and(tile($result, 'late'))->toBe(1)
            ->and(tile($result, 'progress'))->toBe(50.0)
            ->and($rows)->toHaveCount(1)
            ->and($rows[0])->toMatchArray(['progress' => 50, 'tasks_done' => 1, 'tasks_total' => 2, 'overdue_tasks' => 1]);
    });
});

describe('activity and usage', function () {
    it('groups changes by area', function () {
        $organization = Organization::factory()->create();

        inTenant($organization, function () use ($organization) {
            foreach (['task.created', 'task.completed', 'approval.approved', 'workflow.published', 'member.invited', 'purchase_request.submitted'] as $action) {
                ActivityLog::query()->create(['organization_id' => $organization->id, 'actor_id' => $organization->owner_id, 'action' => $action, 'created_at' => '2026-10-07 10:00:00']);
            }
        });

        ['result' => $result, 'rows' => $rows] = runReport($organization, ReportType::Activity);
        $byArea = collect($rows)->pluck('events', 'name');

        expect(tile($result, 'events'))->toBe(6)
            ->and(tile($result, 'people'))->toBe(1)
            ->and($byArea->all())->toMatchArray(['Work' => 2, 'Approvals' => 1, 'Automation' => 1, 'Inventory' => 1, 'Team and settings' => 1]);
    });

    it('counts what the organization uses', function () {
        $organization = Organization::factory()->create();
        memberIn($organization, Role::Employee);
        inTenant($organization, fn () => Task::factory()->count(3)->create(['organization_id' => $organization->id]));

        ['result' => $result, 'rows' => $rows] = runReport($organization, ReportType::OrganizationUsage);

        expect(tile($result, 'members'))->toBe(2)
            ->and(tile($result, 'records'))->toBe(3)
            ->and(collect($rows)->firstWhere('metric', 'Tasks'))->toMatchArray(['in_period' => 3, 'total' => 3]);
    });
});
