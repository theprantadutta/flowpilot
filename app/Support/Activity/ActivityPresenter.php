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
