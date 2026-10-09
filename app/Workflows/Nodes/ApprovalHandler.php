<?php

namespace App\Workflows\Nodes;

use App\Actions\Approvals\RequestApproval;
use App\Enums\ApprovalStatus;
use App\Enums\NodeType;
use App\Enums\Priority;
use App\Enums\Role;
use App\Models\Approval;
use App\Models\User;
use App\Workflows\Definition\ValidationScope;
use App\Workflows\Fields\Field;
use App\Workflows\Support\People;
use App\Workflows\Support\TemplateRenderer;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Asks someone to approve or reject, and waits for the decision.
 *
 *   {
 *     title: "Approve {{ input.item }}",
 *     description: "…",
 *     approver: { type: "role", role: "finance" },
 *     amount_field: "input.amount" | null,
 *     priority: "high",
 *     due_in_hours: 48 | null,
 *     when_overdue: "remind" | "reject"
 *   }
 *
 * A role means anyone holding it can decide. Changes requested keep the run
 * waiting until the requester resubmits and someone decides.
 */
class ApprovalHandler extends BaseHandler
{
    public const int MAX_DUE_HOURS = 720;

    public function __construct(
        private readonly RequestApproval $requestApproval,
        private readonly People $people,
        private readonly TemplateRenderer $renderer,
    ) {}

    public function type(): NodeType
    {
        return NodeType::Approval;
    }

    public function validate(array $config, ValidationScope $scope): array
    {
        $errors = [];
        $title = self::text($config, 'title');

        if ($title === '') {
            $errors[] = 'Write what is being approved.';
        } elseif (mb_strlen($title) > 200) {
            $errors[] = 'Keep the title under 200 characters.';
        }

        if (mb_strlen(self::text($config, 'description')) > 5000) {
            $errors[] = 'Keep the description under 5,000 characters.';
        }

        $approver = $config['approver'] ?? null;

        if (! is_array($approver)) {
            $errors[] = 'Choose who approves.';
        } else {
            $errors = [...$errors, ...People::validate([$approver], $scope->memberIds, $scope->personFields(), $scope->hasSubject(), 'approvers')];

            if (($approver['type'] ?? null) === 'assignee') {
                $errors[] = 'Choose a person, a role or a person field to approve.';
            }
        }

        $amountField = $config['amount_field'] ?? null;

        if ($amountField !== null && $amountField !== '') {
            $field = is_string($amountField) ? Field::find($scope->fields, $amountField) : null;

            if ($field === null || ! in_array($field->type, ['money', 'number'], true)) {
                $errors[] = 'The amount must come from an amount field.';
            }
        }

        if (isset($config['priority']) && Priority::tryFrom((string) $config['priority']) === null) {
            $errors[] = 'Choose a priority.';
        }

        $due = $config['due_in_hours'] ?? null;

        if ($due !== null && $due !== '' && (! is_numeric($due) || (int) $due < 1 || (int) $due > self::MAX_DUE_HOURS)) {
            $errors[] = 'The decision can be due between 1 hour and 30 days after asking.';
        }

        if (! in_array($config['when_overdue'] ?? Approval::WHEN_OVERDUE_REMIND, [Approval::WHEN_OVERDUE_REMIND, Approval::WHEN_OVERDUE_REJECT], true)) {
            $errors[] = 'Choose what happens when nobody decides in time.';
        }

        return [
            ...$errors,
            ...self::templateErrors($title, $scope, 'title'),
            ...self::templateErrors(self::text($config, 'description'), $scope, 'description'),
        ];
    }

    public function execute(StepContext $step): StepResult
    {
        if ($step->step->waiting_on_type === 'approval' && $step->step->waiting_on_id !== null) {
            return $this->checkDecision($step, $step->step->waiting_on_id);
        }

        $config = $step->config();
        $approver = $this->approver($step, $config['approver'] ?? null);
        $requester = $step->run->started_by ? User::query()->find($step->run->started_by) : null;
        $amountField = is_string($config['amount_field'] ?? null) ? $config['amount_field'] : null;
        $amount = $amountField ? Arr::get($step->context, $amountField) : null;
        $due = is_numeric($config['due_in_hours'] ?? null) ? (int) $config['due_in_hours'] : null;

        $approval = $this->requestApproval->handle($requester, [
            'title' => Str::limit($step->render(self::text($config, 'title'), 200), 200, '') ?: $step->label(),
            'description' => $step->render(self::text($config, 'description'), 5000) ?: null,
            'priority' => Priority::tryFrom((string) ($config['priority'] ?? '')) ?? Priority::Medium,
            ...$approver,
            'amount' => is_numeric($amount) ? (int) $amount : null,
            'currency' => is_numeric($amount) ? $step->organization->currency : null,
            'details' => $this->details($step),
            'subject_type' => $step->run->subject_type,
            'subject_id' => $step->run->subject_id,
            'subject_label' => $step->run->subject_label,
            'workflow_run_id' => $step->run->id,
            'workflow_step_run_id' => $step->step->id,
            'when_overdue' => ($config['when_overdue'] ?? null) === Approval::WHEN_OVERDUE_REJECT ? Approval::WHEN_OVERDUE_REJECT : Approval::WHEN_OVERDUE_REMIND,
            'due_at' => $due !== null ? now()->addHours(min($due, self::MAX_DUE_HOURS)) : null,
        ], $step->run);

        return StepResult::wait([
            'approval' => $approval->reference(),
            'approval_url' => route('approvals.show', ['organization' => $step->organization->slug, 'approval' => $approval->id]),
            'approver' => $approval->approver_id !== null ? $approval->approver?->name : 'Anyone in '.$approval->approver_role?->label(),
        ], waitingOnType: 'approval', waitingOnId: $approval->id);
    }

    private function checkDecision(StepContext $step, string $approvalId): StepResult
    {
        $approval = Approval::query()->with('decider:id,name')->find($approvalId)
            ?? throw StepFailed::permanent('The approval request this step was waiting on has been deleted.');

        $output = [
            'approval' => $approval->reference(),
            'approval_url' => route('approvals.show', ['organization' => $step->organization->slug, 'approval' => $approval->id]),
            'decision' => $approval->status->label(),
            'decided_by' => $approval->decider?->name,
            'note' => $approval->decision_note,
        ];

        return match ($approval->status) {
            ApprovalStatus::Approved => StepResult::complete('approved', $output),
            ApprovalStatus::Rejected, ApprovalStatus::Expired, ApprovalStatus::Cancelled => StepResult::complete('rejected', $output),
            default => StepResult::wait($output, waitingOnType: 'approval', waitingOnId: $approval->id),
        };
    }

    /**
     * @return array{approver_id: int|null, approver_role: Role|null}
     */
    private function approver(StepContext $step, mixed $pick): array
    {
        $pick = People::normalize([$pick])[0] ?? null;

        if ($pick !== null && $pick['type'] === 'role' && ($role = Role::tryFrom($pick['role'])) !== null) {
            return ['approver_id' => null, 'approver_role' => $role];
        }

        $user = $pick !== null ? $this->people->resolve($step->organization, [$pick], $step->context)->first() : null;

        if ($user === null) {
            throw StepFailed::permanent('Nobody could be asked to approve: the chosen approver is not an active member.');
        }

        return ['approver_id' => $user->id, 'approver_role' => null];
    }

    /**
     * What the approver sees: the run's details, formatted.
     *
     * @return list<array{label: string, value: string}>
     */
    private function details(StepContext $step): array
    {
        $details = [];

        foreach ($step->fields as $field) {
            if ($field->path === 'actor.id') {
                continue;
            }

            $value = $this->renderer->format(Arr::get($step->context, $field->path), $field, $step->organization);

            if ($value !== '') {
                $details[] = ['label' => $field->label, 'value' => Str::limit($value, 300)];
            }
        }

        if ($step->run->subject_label !== null) {
            array_unshift($details, ['label' => 'Record', 'value' => $step->run->subject_label]);
        }

        return array_slice($details, 0, 15);
    }
}
