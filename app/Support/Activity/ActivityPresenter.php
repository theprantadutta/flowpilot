<?php

namespace App\Support\Activity;

use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Enums\ProjectStatus;
use App\Enums\Role;
use App\Enums\TaskStatus;
use App\Models\ActivityLog;
use Illuminate\Support\Str;

/**
 * Turns activity log entries into sentences for feeds and the audit trail,
 * e.g. "changed Priya Nair's role from Employee to Finance".
 */
class ActivityPresenter
{
    /**
     * @return array{id: string, action: string, actor: string, actor_type: string, summary: string, subject: string|null, tone: string, icon: string, created_at: string, changes: array<string, array{from: mixed, to: mixed}>}
     */
    public function present(ActivityLog $log): array
    {
        $properties = $log->properties ?? [];

        return [
            'id' => $log->id,
            'action' => $log->action,
            'actor' => match ($log->actor_type) {
                'system' => 'FlowPilot',
                'ai' => 'FlowPilot AI',
                'workflow' => ($this->string($properties, 'workflow') ?? 'A').' workflow',
                default => $log->actor->name ?? 'A former member',
            },
            'actor_type' => $log->actor_type,
            'summary' => $this->summary($log, $properties),
            'subject' => $log->subject_label,
            'tone' => $this->tone($log->action),
            'icon' => $this->icon($log->action),
            'created_at' => $log->created_at->toIso8601String(),
            'changes' => $this->changes($properties),
        ];
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function summary(ActivityLog $log, array $properties): string
    {
        $name = $this->string($properties, 'name') ?? $log->subject_label ?? 'someone';
        $email = $this->string($properties, 'email') ?? $log->subject_label ?? 'someone';
        $role = $this->roleLabel($this->string($properties, 'role'));
        $changes = $this->changes($properties);

        return match ($log->action) {
            'organization.created' => 'created the organization',
            'member.invited' => "invited {$email} as {$role}",
            'member.invitation_resent' => "re-sent the invitation to {$email}",
            'member.invitation_revoked' => "withdrew the invitation to {$email}",
            'member.joined' => "joined as {$role}",
            'member.role_changed' => sprintf(
                "changed %s's role from %s to %s",
                $name,
                $this->roleLabel($this->scalar($changes['role']['from'] ?? null)),
                $this->roleLabel($this->scalar($changes['role']['to'] ?? null)),
            ),
            'member.suspended' => "suspended {$name}'s access",
            'member.reactivated' => "restored {$name}'s access",
            'member.updated' => "updated {$name}'s details",
            'member.removed' => "removed {$name} from the organization",
            'settings.updated' => 'updated the '.($this->string($properties, 'section') ?? 'organization').' settings',
            'settings.logo_updated' => 'uploaded a new logo',
            'settings.logo_removed' => 'removed the logo',
            'approval.requested' => sprintf('asked %s to approve %s', $this->string($properties, 'approver') ?? 'someone', $this->workItem($properties, $log)),
            'approval.approved' => "approved {$this->workItem($properties, $log)}".$this->onBehalf($properties),
            'approval.rejected' => "rejected {$this->workItem($properties, $log)}".$this->onBehalf($properties),
            'approval.changes_requested' => "asked for changes to {$this->workItem($properties, $log)}".$this->onBehalf($properties),
            'approval.resubmitted' => "resubmitted {$this->workItem($properties, $log)}",
            'approval.withdrawn' => "withdrew {$this->workItem($properties, $log)}",
            'approval.expired' => "let {$this->workItem($properties, $log)} expire because nobody decided in time",
            'approval.commented' => "commented on {$this->workItem($properties, $log)}",
            'inventory.item_created' => "added {$this->stockItem($properties, $log)} to inventory",
            'inventory.item_updated' => "updated {$this->stockItem($properties, $log)}",
            'inventory.item_archived' => "archived {$this->stockItem($properties, $log)}",
            'inventory.receipt' => sprintf('received %s of %s (%s on hand)', $this->string($properties, 'quantity') ?? 'stock', $this->stockItem($properties, $log), $this->string($properties, 'stock_after') ?? 'new total'),
            'inventory.issue' => sprintf('issued %s of %s (%s left)', $this->string($properties, 'quantity') ?? 'stock', $this->stockItem($properties, $log), $this->string($properties, 'stock_after') ?? 'new total'),
            'inventory.adjustment' => sprintf('corrected the count of %s by %s (%s on hand)', $this->stockItem($properties, $log), $this->string($properties, 'quantity') ?? 'some', $this->string($properties, 'stock_after') ?? 'new total'),
            'inventory.transfer' => sprintf('moved %s of %s between locations', $this->string($properties, 'quantity') ?? 'stock', $this->stockItem($properties, $log)),
            'inventory.supplier_created' => "added the supplier {$this->subjectName($log, $properties)}",
            'inventory.supplier_updated' => "updated the supplier {$this->subjectName($log, $properties)}",
            'inventory.location_created' => "added the location {$this->subjectName($log, $properties)}",
            'inventory.location_updated' => "updated the location {$this->subjectName($log, $properties)}",
            'inventory.category_created' => "added the category {$this->subjectName($log, $properties)}",
            'inventory.category_updated' => "renamed the category {$this->subjectName($log, $properties)}",
            'purchase_request.submitted' => "asked to buy {$this->workItem($properties, $log)}",
            'purchase_request.approved' => "approved {$this->workItem($properties, $log)}",
            'purchase_request.rejected' => "did not approve {$this->workItem($properties, $log)}",
            'purchase_request.ordered' => "ordered {$this->workItem($properties, $log)}",
            'purchase_request.partly_received' => "received part of {$this->workItem($properties, $log)}",
            'purchase_request.received' => "received {$this->workItem($properties, $log)}",
            'purchase_request.cancelled' => "cancelled {$this->workItem($properties, $log)}",
            'settings.webhook_secret_viewed' => 'revealed the webhook signing secret',
            'settings.webhook_secret_rotated' => 'replaced the webhook signing secret',
            'workflow.created' => "created the workflow {$this->subjectName($log, $properties)}",
            'workflow.updated' => "renamed or described {$this->subjectName($log, $properties)}",
            'workflow.published' => sprintf('published version %s of %s', $this->scalar($properties['version'] ?? null) ?? 'a new', $this->subjectName($log, $properties)),
            'workflow.paused' => "paused {$this->subjectName($log, $properties)}",
            'workflow.resumed' => "turned on {$this->subjectName($log, $properties)}",
            'workflow.archived' => "archived {$this->subjectName($log, $properties)}",
            'workflow.deleted' => "deleted the workflow {$this->subjectName($log, $properties)}",
            'workflow.version_restored' => sprintf('copied version %s of %s back into the draft', $this->scalar($properties['version'] ?? null) ?? 'an earlier', $this->subjectName($log, $properties)),
            'workflow.run_started' => sprintf('started %s of %s', $this->string($properties, 'reference') ?? 'a run', $this->string($properties, 'workflow') ?? 'a workflow'),
            'workflow.run_completed' => sprintf('finished %s of %s', $this->string($properties, 'reference') ?? 'a run', $this->string($properties, 'workflow') ?? 'a workflow'),
            'workflow.run_failed' => sprintf('could not finish %s of %s', $this->string($properties, 'reference') ?? 'a run', $this->string($properties, 'workflow') ?? 'a workflow'),
            'workflow.run_cancelled' => sprintf('cancelled %s of %s', $this->string($properties, 'reference') ?? 'a run', $this->string($properties, 'workflow') ?? 'a workflow'),
            'workflow.run_retried' => sprintf('tried %s of %s again', $this->string($properties, 'reference') ?? 'a run', $this->string($properties, 'workflow') ?? 'a workflow'),
            'project.created' => "created the project {$this->subjectName($log, $properties)}",
            'project.updated' => "updated {$this->subjectName($log, $properties)}",
            'project.status_changed' => sprintf(
                'moved %s from %s to %s',
                $this->subjectName($log, $properties),
                $this->enumLabel(ProjectStatus::class, $changes['status']['from'] ?? null),
                $this->enumLabel(ProjectStatus::class, $changes['status']['to'] ?? null),
            ),
            'project.deleted' => "deleted the project {$this->subjectName($log, $properties)}",
            'task.created' => "created {$this->workItem($properties, $log)}",
            'task.updated' => "updated {$this->workItem($properties, $log)}",
            'task.completed' => "completed {$this->workItem($properties, $log)}",
            'task.status_changed' => sprintf(
                'moved %s to %s',
                $this->workItem($properties, $log),
                $this->enumLabel(TaskStatus::class, $changes['status']['to'] ?? null),
            ),
            'task.assigned' => "reassigned {$this->workItem($properties, $log)}",
            'task.deleted' => "deleted {$this->workItem($properties, $log)}",
            'task.commented' => "commented on {$this->workItem($properties, $log)}",
            'task.checklist_completed' => "finished the checklist on {$this->workItem($properties, $log)}",
            'task.dependency_added' => sprintf('marked %s as waiting on %s', $this->workItem($properties, $log), $this->string($properties, 'blocker') ?? 'another task'),
            'task.dependency_removed' => sprintf('removed the dependency of %s on %s', $this->workItem($properties, $log), $this->string($properties, 'blocker') ?? 'another task'),
            'issue.created' => "reported {$this->workItem($properties, $log)}",
            'issue.updated' => "updated {$this->workItem($properties, $log)}",
            'issue.resolved' => "resolved {$this->workItem($properties, $log)}",
            'issue.status_changed' => sprintf(
                'marked %s as %s',
                $this->workItem($properties, $log),
                $this->enumLabel(IssueStatus::class, $changes['status']['to'] ?? null),
            ),
            'issue.severity_changed' => sprintf(
                'changed the severity of %s to %s',
                $this->workItem($properties, $log),
                $this->enumLabel(IssueSeverity::class, $changes['severity']['to'] ?? null),
            ),
            'issue.assigned' => "reassigned {$this->workItem($properties, $log)}",
            'issue.deleted' => "deleted {$this->workItem($properties, $log)}",
            'issue.commented' => "commented on {$this->workItem($properties, $log)}",
            'file.uploaded' => sprintf('attached %s to %s', $this->string($properties, 'file') ?? 'a file', $this->string($properties, 'title') ?? 'a record'),
            'file.deleted' => sprintf('removed %s from %s', $this->string($properties, 'file') ?? 'a file', $this->string($properties, 'title') ?? 'a record'),
            default => Str::of($log->action)->replace(['.', '_'], ' ')->lower()->toString(),
        };
    }

    private function tone(string $action): string
    {
        return match (true) {
            str_ends_with($action, '.removed'), str_ends_with($action, '.suspended'), str_ends_with($action, '.deleted'),
            str_ends_with($action, '.failed'), str_ends_with($action, '.rejected') => 'danger',
            str_ends_with($action, '.approved'), str_ends_with($action, '.completed'), str_ends_with($action, '.resolved'),
            str_ends_with($action, '.joined'), $action === 'organization.created' => 'success',
            str_starts_with($action, 'workflow.') => 'flow',
            str_starts_with($action, 'ai.') => 'ai',
            str_starts_with($action, 'settings.') => 'neutral',
            default => 'info',
        };
    }

    private function icon(string $action): string
    {
        return match (Str::before($action, '.')) {
            'member' => 'user',
            'settings' => 'settings',
            'organization' => 'building',
            'project' => 'folder',
            'task' => 'check-square',
            'issue' => 'alert-triangle',
            'workflow' => 'git-branch',
            'approval' => 'stamp',
            'purchase_request' => 'truck',
            'inventory' => 'package',
            'ai' => 'sparkles',
            'file' => 'paperclip',
            default => 'activity',
        };
    }

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, array{from: mixed, to: mixed}>
     */
    private function changes(array $properties): array
    {
        $changes = $properties['changes'] ?? [];

        if (! is_array($changes)) {
            return [];
        }

        $clean = [];

        foreach ($changes as $field => $change) {
            if (is_string($field) && is_array($change) && array_key_exists('to', $change)) {
                $clean[$field] = ['from' => $change['from'] ?? null, 'to' => $change['to']];
            }
        }

        return $clean;
    }

    /**
     * "T-42 Install conveyor sensors".
     *
     * @param  array<string, mixed>  $properties
     */
    private function workItem(array $properties, ActivityLog $log): string
    {
        $reference = $this->string($properties, 'reference');
        $title = $this->string($properties, 'title') ?? $log->subject_label ?? 'an item';

        return $reference ? "{$reference} {$title}" : $title;
    }

    /**
     * "SKU-123 Nitrile gloves".
     *
     * @param  array<string, mixed>  $properties
     */
    private function stockItem(array $properties, ActivityLog $log): string
    {
        $sku = $this->string($properties, 'sku');
        $name = $this->string($properties, 'name') ?? $log->subject_label ?? 'an item';

        return $sku ? "{$sku} {$name}" : $name;
    }

    /**
     * ", for Priya Nair" when someone decided on another person's behalf.
     *
     * @param  array<string, mixed>  $properties
     */
    private function onBehalf(array $properties): string
    {
        $name = $this->string($properties, 'on_behalf');

        return $name ? " on behalf of {$name}" : '';
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function subjectName(ActivityLog $log, array $properties): string
    {
        return $this->string($properties, 'name') ?? $log->subject_label ?? 'a project';
    }

    /**
     * @param  class-string<ProjectStatus|TaskStatus|IssueStatus|IssueSeverity>  $enum
     */
    private function enumLabel(string $enum, mixed $value): string
    {
        return is_string($value) ? ($enum::tryFrom($value)?->label() ?? $value) : 'another state';
    }

    private function roleLabel(?string $value): string
    {
        return ($value ? Role::tryFrom($value)?->label() : null) ?? 'a member';
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function string(array $properties, string $key): ?string
    {
        $value = $properties[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function scalar(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }
}
