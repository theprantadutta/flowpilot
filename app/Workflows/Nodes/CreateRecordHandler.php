<?php

namespace App\Workflows\Nodes;

use App\Actions\Inventory\SubmitPurchaseRequest;
use App\Actions\Issues\CreateIssue;
use App\Actions\Tasks\CreateTask;
use App\Enums\IssueSeverity;
use App\Enums\NodeType;
use App\Enums\Priority;
use App\Models\InventoryItem;
use App\Models\Project;
use App\Workflows\Definition\ValidationScope;
use App\Workflows\Support\People;
use App\Workflows\Support\RecordLinks;
use Illuminate\Support\Str;

/**
 * Creates a task or an issue, or (when the run is about a stock item) a
 * purchase request to reorder it.
 *
 *   {
 *     record: "task" | "issue",
 *     title: "Prepare PO for {{ subject.title }}",
 *     description: "…",
 *     priority: "high",            (tasks)
 *     severity: "critical",        (issues)
 *     assignee: { type: "role", role: "procurement" } | null,
 *     project: "subject" | "<project id>" | null,
 *     due_in_days: 3 | null,
 *     tags: ["purchase"]
 *   }
 *
 *   { record: "purchase_request", quantity: 50 | null, reason: "…" }
 *     A null quantity orders the item's reorder quantity.
 */
class CreateRecordHandler extends BaseHandler
{
    public const array RECORDS = ['task', 'issue', 'purchase_request'];

    public function __construct(
        private readonly CreateTask $createTask,
        private readonly CreateIssue $createIssue,
        private readonly SubmitPurchaseRequest $submitPurchaseRequest,
        private readonly People $people,
    ) {}

    public function type(): NodeType
    {
        return NodeType::CreateRecord;
    }

    public function validate(array $config, ValidationScope $scope): array
    {
        $record = $config['record'] ?? null;

        if (! in_array($record, self::RECORDS, true)) {
            return ['Choose whether to create a task or an issue.'];
        }

        if ($record === 'purchase_request') {
            return $this->validatePurchaseRequest($config, $scope);
        }

        $errors = [];
        $title = self::text($config, 'title');
        $description = self::text($config, 'description');

        if ($title === '') {
            $errors[] = 'Write a title for the new '.$record.'.';
        } elseif (mb_strlen($title) > 200) {
            $errors[] = 'Keep the title under 200 characters.';
        }

        if (mb_strlen($description) > 5000) {
            $errors[] = 'Keep the description under 5,000 characters.';
        }

        if ($record === 'task' && isset($config['priority']) && Priority::tryFrom((string) $config['priority']) === null) {
            $errors[] = 'Choose a priority.';
        }

        if ($record === 'issue' && isset($config['severity']) && IssueSeverity::tryFrom((string) $config['severity']) === null) {
            $errors[] = 'Choose a severity.';
        }

        if (($config['assignee'] ?? null) !== null) {
            $errors = [...$errors, ...People::validate([$config['assignee']], $scope->memberIds, $scope->personFields(), $scope->subjectIsWork(), 'assignees')];
        }

        $project = $config['project'] ?? null;

        if ($project === 'subject' && ! $scope->subjectIsWork()) {
            $errors[] = 'Only tasks and issues have a project to copy.';
        } elseif (is_string($project) && $project !== 'subject' && ! in_array($project, $scope->projectIds, true)) {
            $errors[] = 'The chosen project no longer exists.';
        }

        $due = $config['due_in_days'] ?? null;

        if ($due !== null && $due !== '' && (! is_numeric($due) || (int) $due < 0 || (int) $due > 365)) {
            $errors[] = 'The due date must be between 0 and 365 days away.';
        }

        $tags = $config['tags'] ?? [];

        if (! is_array($tags) || count($tags) > 10) {
            $errors[] = 'Use at most 10 tags.';
        }

        return [
            ...$errors,
            ...self::templateErrors($title, $scope, 'title'),
            ...self::templateErrors($description, $scope, 'description'),
        ];
    }

    public function execute(StepContext $step): StepResult
    {
        $config = $step->config();

        if (($config['record'] ?? null) === 'purchase_request') {
            return $this->reorder($step, $config);
        }

        $record = $config['record'] === 'issue' ? 'issue' : 'task';

        $assignee = ($config['assignee'] ?? null) !== null
            ? $this->people->pickOne($step->organization, $config['assignee'], $step->context)
            : null;

        $attributes = [
            'title' => Str::limit($step->render(self::text($config, 'title'), 200), 200, '') ?: $step->label(),
            'description' => $step->render(self::text($config, 'description'), 5000) ?: null,
            'assignee_id' => $assignee?->id,
            'project_id' => $this->projectId($config['project'] ?? null, $step),
            'due_date' => is_numeric($config['due_in_days'] ?? null)
                ? now($step->organization->timezone)->addDays((int) $config['due_in_days'])->toDateString()
                : null,
            'tags' => $this->tags($config['tags'] ?? []),
        ];

        if ($record === 'task') {
            $created = $this->createTask->handle(null, [
                ...$attributes,
                'priority' => Priority::tryFrom((string) ($config['priority'] ?? '')) ?? Priority::Medium,
            ], $step->run);
        } else {
            $created = $this->createIssue->handle(null, [
                ...$attributes,
                'severity' => IssueSeverity::tryFrom((string) ($config['severity'] ?? '')) ?? IssueSeverity::Medium,
            ], $step->run);
        }

        return StepResult::complete('next', [
            'record_type' => $record,
            'id' => $created->id,
            'reference' => $created->reference(),
            'title' => $created->title,
            'url' => RecordLinks::for($created, $step->organization),
            'assignee' => $assignee?->name,
        ]);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    private function validatePurchaseRequest(array $config, ValidationScope $scope): array
    {
        $errors = [];

        if ($scope->subjectType() !== 'inventory_item') {
            $errors[] = 'Purchase requests can only be raised from a trigger about a stock item, such as Stock runs low.';
        }

        $quantity = $config['quantity'] ?? null;

        if ($quantity !== null && $quantity !== '' && (! is_numeric($quantity) || (int) $quantity < 1 || (int) $quantity > 1_000_000)) {
            $errors[] = 'Order at least one.';
        }

        $reason = self::text($config, 'reason');

        if (mb_strlen($reason) > 2000) {
            $errors[] = 'Keep the reason under 2,000 characters.';
        }

        return [...$errors, ...self::templateErrors($reason, $scope, 'reason')];
    }

    /**
     * Raise a purchase request for the stock item the run is about.
     *
     * @param  array<string, mixed>  $config
     */
    private function reorder(StepContext $step, array $config): StepResult
    {
        $item = $step->subjectOrFail();

        if (! $item instanceof InventoryItem) {
            throw StepFailed::permanent('Purchase requests can only be raised for stock items.');
        }

        $quantity = is_numeric($config['quantity'] ?? null) ? (int) $config['quantity'] : max(1, $item->reorder_quantity);

        $request = $this->submitPurchaseRequest->handle(null, [
            'inventory_item_id' => $item->id,
            'item_name' => $item->name,
            'supplier_id' => $item->supplier_id,
            'deliver_to_location_id' => $item->default_location_id,
            'quantity' => $quantity,
            'unit_cost_amount' => $item->unit_cost_amount ?? 0,
            'reason' => $step->render(self::text($config, 'reason'), 2000) ?: null,
            // One request per run step, however often the step is retried.
            'idempotency_key' => 'workflow:'.$step->step->id,
        ], $step->run);

        return StepResult::complete('next', [
            'record_type' => 'purchase_request',
            'id' => $request->id,
            'reference' => $request->reference(),
            'title' => $request->summary(),
            'url' => RecordLinks::for($request, $step->organization),
        ]);
    }

    private function projectId(mixed $project, StepContext $step): ?string
    {
        $id = $project === 'subject' ? data_get($step->context, 'subject.project_id') : $project;

        if (! is_string($id) || ! Str::isUuid($id)) {
            return null;
        }

        // A project deleted since publishing just leaves the record unfiled.
        return Project::query()->whereKey($id)->exists() ? $id : null;
    }

    /**
     * @return list<string>|null
     */
    private function tags(mixed $tags): ?array
    {
        $clean = array_values(array_unique(array_filter(array_map(
            fn (mixed $tag): string => is_string($tag) ? Str::of($tag)->squish()->lower()->limit(30, '')->toString() : '',
            is_array($tags) ? array_slice($tags, 0, 10) : [],
        ))));

        return $clean === [] ? null : $clean;
    }
}
