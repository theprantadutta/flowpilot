<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\Task;

class TaskOverdueNotification extends TenantNotification
{
    public string $taskId;

    public string $reference;

    public string $taskTitle;

    public string $dueDate;

    public function __construct(Task $task)
    {
        parent::__construct();

        $this->taskId = $task->id;
        $this->reference = $task->reference();
        $this->taskTitle = $task->title;
        $this->dueDate = $task->due_date?->toFormattedDayDateString() ?? '';
    }

    public function type(): NotificationType
    {
        return NotificationType::TaskOverdue;
    }

    public function title(): string
    {
        return "{$this->reference} is overdue: {$this->taskTitle}";
    }

    public function body(): ?string
    {
        return "It was due {$this->dueDate}. Update the due date or mark it done so the team knows where it stands.";
    }

    public function url(): ?string
    {
        return $this->tenantRoute('tasks.show', ['task' => $this->taskId]);
    }

    public function tone(): string
    {
        return 'danger';
    }

    public function actionText(): string
    {
        return 'Open task';
    }
}
