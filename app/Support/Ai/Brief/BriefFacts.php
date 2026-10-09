<?php

namespace App\Support\Ai\Brief;

use App\Enums\ApprovalStatus;
use App\Enums\IssueSeverity;
use App\Enums\Permission;
use App\Enums\ProjectStatus;
use App\Enums\PurchaseRequestStatus;
use App\Enums\WorkflowRunStatus;
use App\Models\Approval;
use App\Models\InventoryItem;
use App\Models\Issue;
use App\Models\Project;
use App\Models\PurchaseRequest;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkflowRun;
use App\Support\Money;
use App\Support\Tenancy\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * The facts an operations brief may be written from: only what the member is
 * allowed to see, a few per area, titles cut short, no free text beyond a
 * record's name. Each fact has an id the model must cite, so every line of a
 * brief links back to a real record.
 *
 * @phpstan-type Fact array{id: string, area: string, priority: int, label: string, url: string|null, data: array<string, string|int|float|bool|null>}
 */
class BriefFacts
{
    public function __construct(private readonly Tenancy $tenancy) {}

    /**
     * @return list<Fact>
     */
    public function for(User $user): array
    {
        $limit = max(1, (int) config('ai.brief.facts_per_area', 6));
        $organization = $this->tenancy->currentOrFail();
        $today = CarbonImmutable::now($organization->timezone)->startOfDay();

        $facts = [
            ...($user->can(Permission::TasksView->value) ? $this->tasks($user, $today, $limit) : []),
            ...($user->can(Permission::ApprovalsView->value) ? $this->approvals($user, $limit) : []),
            ...($user->can(Permission::IssuesView->value) ? $this->issues($limit) : []),
            ...($user->can(Permission::WorkflowsView->value) ? $this->failedRuns($limit) : []),
            ...($user->can(Permission::InventoryView->value) ? $this->stock($limit) : []),
            ...($user->can(Permission::InventoryManage->value) ? $this->purchases($user, $limit) : []),
            ...($user->can(Permission::ProjectsView->value) ? $this->projects($today, $limit) : []),
        ];

        usort($facts, fn (array $a, array $b): int => $a['priority'] <=> $b['priority']);

        return $facts;
    }

    /**
     * @return list<Fact>
     */
    private function tasks(User $user, CarbonImmutable $today, int $limit): array
    {
        $facts = [];

        $tasks = Task::query()
            ->overdue($today->toDateString())
            ->where('assignee_id', $user->id)
            ->with('project:id,organization_id,name')
            ->orderBy('due_date')
            ->limit($limit)
            ->get();

        foreach ($tasks as $task) {
            $facts[] = $this->fact("task:{$task->reference()}", 'tasks', 1, "{$task->reference()} {$task->title}", $this->url('tasks.show', ['task' => $task->id]), [
                'assigned_to' => 'the reader',
                'due' => $task->due_date?->toDateString(),
                'days_late' => $task->due_date !== null ? (int) $task->due_date->diffInDays($today) : null,
                'project' => $task->project?->name,
            ]);
        }

        $overdue = Task::query()->overdue($today->toDateString())->count();

        if ($overdue > count($tasks)) {
            $facts[] = $this->fact('tasks:overdue', 'tasks', 2, 'Overdue tasks across the organization', $this->url('tasks.index', ['due' => 'overdue']), [
                'count' => $overdue,
            ]);
        }

        return $facts;
    }

    /**
     * Requests waiting for the reader's decision, and the reader's own
     * requests that have been waiting more than a day.
     *
     * @return list<Fact>
     */
    private function approvals(User $user, int $limit): array
    {
        $facts = [];

        $waiting = Approval::query()
            ->waitingOn($user, $this->tenancy->membershipFor($user))
            ->with('requester:id,name')
            ->oldest()
            ->limit($limit)
            ->get();

        foreach ($waiting as $approval) {
            $facts[] = $this->approvalFact($approval, $approval->due_at !== null && $approval->due_at->isPast() ? 1 : 2, [
                'waiting_for' => 'the reader to decide',
                'requested_by' => $approval->requester?->name,
            ]);
        }

        $mine = Approval::query()
            ->where('requester_id', $user->id)
            ->where('status', ApprovalStatus::Pending->value)
            ->where('created_at', '<=', now()->subDay())
            ->with('approver:id,name')
            ->oldest()
            ->limit($limit)
            ->get();

        foreach ($mine as $approval) {
            $facts[] = $this->approvalFact($approval, 2, [
                'requested_by' => 'the reader',
                'waiting_for' => $approval->approver->name ?? ($approval->approver_role !== null ? 'anyone in '.$approval->approver_role->label() : 'an approver'),
            ]);
        }

        return $facts;
    }

    /**
     * @param  array<string, string|null>  $extra
     * @return Fact
     */
    private function approvalFact(Approval $approval, int $priority, array $extra): array
    {
        return $this->fact("approval:{$approval->reference()}", 'approvals', $priority, Str::limit("{$approval->reference()} {$approval->title}", 140), $this->url('approvals.show', ['approval' => $approval->id]), [
            ...$extra,
            'waiting_hours' => (int) $approval->created_at?->diffInHours(now()),
            'overdue' => $approval->due_at !== null && $approval->due_at->isPast(),
            'amount' => $approval->amount !== null && $approval->currency !== null ? Money::format($approval->amount, $approval->currency) : null,
        ]);
    }

    /**
     * @return list<Fact>
     */
    private function issues(int $limit): array
    {
        return array_values(Issue::query()
            ->open()
            ->whereIn('severity', [IssueSeverity::Critical->value, IssueSeverity::High->value])
            ->with('assignee:id,name')
            ->orderByRaw("case severity when 'critical' then 0 else 1 end")
            ->oldest()
            ->limit($limit)
            ->get()
            ->map(fn (Issue $issue): array => $this->fact("issue:{$issue->reference()}", 'issues', $issue->severity === IssueSeverity::Critical ? 1 : 2, Str::limit("{$issue->reference()} {$issue->title}", 140), $this->url('issues.show', ['issue' => $issue->id]), [
                'severity' => $issue->severity->label(),
                'open_days' => (int) $issue->created_at?->diffInDays(now()),
                'assigned_to' => $issue->assignee->name ?? 'nobody',
            ]))
            ->all());
    }

    /**
     * @return list<Fact>
     */
    private function failedRuns(int $limit): array
    {
        return array_values(WorkflowRun::query()
            ->where('status', WorkflowRunStatus::Failed->value)
            ->where('failed_at', '>=', now()->subDays(7))
            ->with('workflow:id,organization_id,name')
            ->latest('failed_at')
            ->limit($limit)
            ->get()
            ->map(fn (WorkflowRun $run): array => $this->fact("run:{$run->reference()}", 'workflows', 1, "{$run->reference()} of ".($run->workflow->name ?? 'a workflow'), $this->url('workflow-runs.show', ['run' => $run->id]), [
                'failed_hours_ago' => (int) $run->failed_at?->diffInHours(now()),
                'about' => $run->subject_label,
                'error' => Str::limit((string) $run->error, 160),
            ]))
            ->all());
    }

    /**
     * Items at or below their reorder point, and whether a purchase is
     * already on its way.
     *
     * @return list<Fact>
     */
    private function stock(int $limit): array
    {
        $open = [PurchaseRequestStatus::Submitted->value, PurchaseRequestStatus::Approved->value, PurchaseRequestStatus::Ordered->value];

        return array_values(InventoryItem::query()
            ->where('is_active', true)
            ->whereColumn('current_stock', '<=', 'reorder_point')
            ->withExists(['purchaseRequests as on_order' => fn (Builder $requests) => $requests->whereIn('status', $open)])
            ->orderByRaw('case when current_stock <= 0 then 0 else 1 end')
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (InventoryItem $item): array => $this->fact("item:{$item->sku}", 'inventory', $item->current_stock <= 0 ? 1 : 2, Str::limit("{$item->sku} {$item->name}", 140), $this->url('inventory.items.show', ['item' => $item->id]), [
                'on_hand' => $item->unit->quantity($item->current_stock),
                'reorder_point' => $item->reorder_point,
                'reorder_quantity' => $item->reorder_quantity,
                'purchase_already_requested' => (bool) $item->getAttribute('on_order'),
            ]))
            ->all());
    }

    /**
     * @return list<Fact>
     */
    private function purchases(User $reader, int $limit): array
    {
        return array_values(PurchaseRequest::query()
            ->where('status', PurchaseRequestStatus::Submitted->value)
            ->with('requester:id,name')
            ->oldest()
            ->limit($limit)
            ->get()
            ->map(fn (PurchaseRequest $purchase): array => $this->fact("purchase:{$purchase->reference()}", 'inventory', 2, Str::limit("{$purchase->reference()} {$purchase->summary()}", 140), $this->url('purchase-requests.show', ['purchaseRequest' => $purchase->id]), [
                'total' => Money::format($purchase->total_amount, $purchase->currency),
                'waiting_hours' => (int) $purchase->created_at?->diffInHours(now()),
                // Nobody approves their own request.
                ...($purchase->requester_id === $reader->id
                    ? ['requested_by' => 'the reader', 'waiting_for' => 'another inventory manager to approve it']
                    : ['requested_by' => $purchase->requester->name ?? 'a workflow', 'waiting_for' => 'the reader to approve or reject it']),
            ]))
            ->all());
    }

    /**
     * Open projects past their due date, or due within two weeks and less
     * than half done.
     *
     * @return list<Fact>
     */
    private function projects(CarbonImmutable $today, int $limit): array
    {
        return array_values(Project::query()
            ->whereIn('status', array_map(fn (ProjectStatus $status): string => $status->value, ProjectStatus::open()))
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', $today->addDays(14)->toDateString())
            ->withProgress()
            ->orderBy('due_date')
            ->get()
            ->filter(fn (Project $project): bool => $project->due_date !== null && ($project->due_date->lessThan($today) || $project->progress() < 50))
            ->take($limit)
            ->map(fn (Project $project): array => $this->fact('project:'.Str::substr($project->id, -8), 'projects', $project->due_date !== null && $project->due_date->lessThan($today) ? 1 : 2, Str::limit($project->name, 140), $this->url('projects.show', ['project' => $project->id]), [
                'due' => $project->due_date?->toDateString(),
                'past_due' => $project->due_date !== null && $project->due_date->lessThan($today),
                'progress_percent' => $project->progress(),
                'open_issues' => (int) $project->getAttribute('open_issues_count'),
            ]))
            ->all());
    }

    /**
     * @param  array<string, string|int|float|bool|null>  $data
     * @return Fact
     */
    private function fact(string $id, string $area, int $priority, string $label, ?string $url, array $data): array
    {
        return [
            'id' => $id,
            'area' => $area,
            'priority' => $priority,
            'label' => $label,
            'url' => $url,
            'data' => array_filter($data, fn (mixed $value): bool => $value !== null),
        ];
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function url(string $name, array $parameters): string
    {
        return route($name, ['organization' => $this->tenancy->currentOrFail()->slug, ...$parameters]);
    }
}
