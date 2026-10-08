<?php

namespace App\Actions\Issues;

use App\Models\Issue;
use App\Models\User;
use App\Notifications\IssueAssignedNotification;
use App\Support\Activity\ActivityLogger;
use App\Support\Tenancy\OrganizationSequence;
use Illuminate\Support\Facades\DB;

class CreateIssue
{
    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly OrganizationSequence $sequence,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes  Validated issue attributes.
     */
    public function handle(User $actor, array $attributes): Issue
    {
        $issue = DB::transaction(function () use ($actor, $attributes): Issue {
            $issue = Issue::query()->create([
                ...$attributes,
                'number' => $this->sequence->next('issues'),
                'reporter_id' => $actor->id,
            ]);

            $this->activity->log('issue.created', $issue, [
                'title' => $issue->title,
                'reference' => $issue->reference(),
                'severity' => $issue->severity->value,
            ], context: $issue->project, actor: $actor);

            return $issue;
        });

        if ($issue->assignee_id !== null && $issue->assignee_id !== $actor->id) {
            $issue->assignee?->notify(IssueAssignedNotification::by($issue, $actor));
        }

        return $issue;
    }
}
