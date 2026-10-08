<?php

namespace App\Actions\Issues;

use App\Enums\IssueStatus;
use App\Models\Issue;
use App\Models\User;
use App\Models\WorkflowRun;
use App\Notifications\IssueAssignedNotification;
use App\Support\Activity\ActivityLogger;
use Illuminate\Support\Facades\DB;

class UpdateIssue
{
    public function __construct(private readonly ActivityLogger $activity) {}

    /**
     * @param  array<string, mixed>  $attributes  Validated attributes to change.
     * @param  WorkflowRun|null  $automation  The workflow run making the change, if a workflow is.
     */
    public function handle(Issue $issue, ?User $actor, array $attributes, ?WorkflowRun $automation = null): Issue
    {
        $previousAssignee = $issue->assignee_id;

        DB::transaction(function () use ($issue, $actor, $attributes, $automation): void {
            $before = $this->snapshot($issue);

            $issue->fill($attributes);

            if ($issue->isDirty('status')) {
                $issue->resolved_at = $issue->status->isOpen() ? null : ($issue->resolved_at ?? now());
            }

            $issue->save();

            $changes = ActivityLogger::diff($before, $this->snapshot($issue));

            if ($changes !== []) {
                $action = match (true) {
                    isset($changes['status']) && $changes['status']['to'] === IssueStatus::Resolved->value => 'issue.resolved',
                    isset($changes['status']) => 'issue.status_changed',
                    isset($changes['severity']) => 'issue.severity_changed',
                    isset($changes['assignee_id']) => 'issue.assigned',
                    default => 'issue.updated',
                };

                $this->activity->log($action, $issue, [
                    'title' => $issue->title,
                    'reference' => $issue->reference(),
                    'changes' => $changes,
                    ...ActivityLogger::automation($automation),
                ], context: $issue->project, actor: $actor, actorType: $automation ? 'workflow' : 'user');
            }
        });

        if ($issue->assignee_id !== null && $issue->assignee_id !== $previousAssignee && $issue->assignee_id !== $actor?->id) {
            $issue->assignee?->notify(new IssueAssignedNotification($issue, $automation ? ActivityLogger::automation($automation)['workflow'] : $actor?->name));
        }

        return $issue;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Issue $issue): array
    {
        return [
            'title' => $issue->title,
            'description' => $issue->description,
            'severity' => $issue->severity->value,
            'status' => $issue->status->value,
            'assignee_id' => $issue->assignee_id,
            'project_id' => $issue->project_id,
            'due_date' => $issue->due_date?->toDateString(),
            'tags' => $issue->tags,
        ];
    }
}
