<?php

namespace App\Workflows\Triggers;

use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Models\Issue;
use App\Workflows\Fields\Field;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class IssueCreatedTrigger extends Trigger
{
    public function key(): string
    {
        return 'issue.created';
    }

    public function label(): string
    {
        return 'Issue is reported';
    }

    public function description(): string
    {
        return 'Runs whenever someone reports an issue.';
    }

    public function subjectType(): string
    {
        return 'issue';
    }

    protected function ownFields(array $config): array
    {
        return [
            new Field('subject.title', 'Issue title', 'text'),
            new Field('subject.reference', 'Issue reference', 'text'),
            new Field('subject.severity', 'Issue severity', 'select', Field::optionsFrom(IssueSeverity::options())),
            new Field('subject.status', 'Issue status', 'select', Field::optionsFrom(IssueStatus::options())),
            new Field('subject.assignee_id', 'Issue assignee', 'person'),
            new Field('subject.reporter_id', 'Reported by', 'person'),
            new Field('subject.project', 'Project name', 'text'),
            new Field('subject.due_date', 'Issue due date', 'date'),
            new Field('subject.tags', 'Issue tags', 'text'),
        ];
    }

    public function snapshot(Model $subject): array
    {
        if (! $subject instanceof Issue) {
            throw new InvalidArgumentException('Issue triggers need an issue.');
        }

        $subject->loadMissing(['project:id,organization_id,name', 'assignee:id,name']);

        return [
            'id' => $subject->id,
            'reference' => $subject->reference(),
            'title' => $subject->title,
            'description' => $subject->description,
            'severity' => $subject->severity->value,
            'status' => $subject->status->value,
            'assignee_id' => $subject->assignee_id,
            'assignee_name' => $subject->assignee?->name,
            'reporter_id' => $subject->reporter_id,
            'project_id' => $subject->project_id,
            'project' => $subject->project?->name,
            'due_date' => $subject->due_date?->toDateString(),
            'tags' => $subject->tags ?? [],
        ];
    }

    public function subjectLabel(Model $subject): ?string
    {
        return $subject instanceof Issue ? "{$subject->reference()} {$subject->title}" : null;
    }
}
