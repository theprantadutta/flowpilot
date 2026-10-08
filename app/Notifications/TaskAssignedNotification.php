<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\Task;
use App\Models\User;

class TaskAssignedNotification extends TenantNotification
{
    public string $taskId;

    public string $reference;

    public string $taskTitle;

    public ?string $dueDate;

    public ?string $projectName;

    public function __construct(Task $task, public ?string $assignedBy = null)
    {
        parent::__construct();

        $this->taskId = $task->id;
        $this->reference = $task->reference();
        $this->taskTitle = $task->title;
        $this->dueDate = $task->due_date?->toFormattedDayDateString();
        $this->projectName = $task->project?->name;
    }

    public static function by(Task $task, ?User $actor): self
    {
        return new self($task, $actor?->name);
    }

    public function type(): NotificationType
    {
        return NotificationType::TaskAssigned;
    }

    public function title(): string
    {
        return "{$this->reference}: {$this->taskTitle}";
    }

    public function body(): ?string
    {
        $parts = [($this->assignedBy ?? 'Someone').' assigned this task to you'];

        if ($this->projectName) {
            $parts[] = "in {$this->projectName}";
        }

        $sentence = implode(' ', $parts).'.';

        return $this->dueDate ? "{$sentence} Due {$this->dueDate}." : $sentence;
    }

    public function url(): ?string
    {
        return $this->tenantRoute('tasks.show', ['task' => $this->taskId]);
    }

    public function actorName(): ?string
    {
        return $this->assignedBy;
    }

    public function actionText(): string
    {
        return 'Open task';
    }
}
