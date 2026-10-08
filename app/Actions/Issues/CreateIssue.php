<?php

namespace App\Actions\Issues;

use App\Models\Issue;
use App\Models\User;
use App\Models\WorkflowRun;
use App\Notifications\IssueAssignedNotification;
use App\Support\Activity\ActivityLogger;
use App\Support\Tenancy\OrganizationSequence;
use App\Workflows\Engine\WorkflowTriggers;
use Illuminate\Support\Facades\DB;

class CreateIssue
{
    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly OrganizationSequence $sequence,
        private readonly WorkflowTriggers $triggers,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes  Validated issue attributes.
     * @param  WorkflowRun|null  $automation  The workflow run raising the issue, if a workflow is.
     */
    public function handle(?User $actor, array $attributes, ?WorkflowRun $automation = null): Issue
    {
        $issue = DB::transaction(function () use ($actor, $attributes, $automation): Issue {
            $issue = Issue::query()->create([
                ...$attributes,
                'number' => $this->sequence->next('issues'),
                'reporter_id' => $actor?->id,
            ]);

            $this->activity->log('issue.created', $issue, [
                'title' => $issue->title,
                'reference' => $issue->reference(),
                'severity' => $issue->severity->value,
                ...ActivityLogger::automation($automation),
            ], context: $issue->project, actor: $actor, actorType: $automation ? 'workflow' : 'user');

            $this->triggers->fire('issue.created', $issue, $actor, $automation);

            return $issue;
        });

        if ($issue->assignee_id !== null && $issue->assignee_id !== $actor?->id) {
            $issue->assignee?->notify(new IssueAssignedNotification($issue, $automation ? ActivityLogger::automation($automation)['workflow'] : $actor?->name));
        }

        return $issue;
    }
}
