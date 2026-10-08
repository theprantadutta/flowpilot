<?php

namespace App\Workflows\Triggers;

use App\Enums\Priority;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Workflows\Fields\Field;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Triggers fired by something happening to a task.
 */
abstract class TaskTrigger extends Trigger
{
    public function subjectType(): string
    {
        return 'task';
    }

    protected function ownFields(array $config): array
    {
        return [
            new Field('subject.title', 'Task title', 'text'),
            new Field('subject.reference', 'Task reference', 'text'),
            new Field('subject.status', 'Task status', 'select', Field::optionsFrom(TaskStatus::options())),
            new Field('subject.priority', 'Task priority', 'select', Field::optionsFrom(Priority::options())),
            new Field('subject.assignee_id', 'Task assignee', 'person'),
            new Field('subject.reporter_id', 'Task created by', 'person'),
            new Field('subject.project', 'Project name', 'text'),
            new Field('subject.due_date', 'Task due date', 'date'),
            new Field('subject.tags', 'Task tags', 'text'),
        ];
    }

    public function snapshot(Model $subject): array
    {
        if (! $subject instanceof Task) {
            throw new InvalidArgumentException('Task triggers need a task.');
        }

        $subject->loadMissing(['project:id,organization_id,name', 'assignee:id,name']);

        return [
            'id' => $subject->id,
            'reference' => $subject->reference(),
            'title' => $subject->title,
            'description' => $subject->description,
            'status' => $subject->status->value,
            'priority' => $subject->priority->value,
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
        return $subject instanceof Task ? "{$subject->reference()} {$subject->title}" : null;
    }
}
