<?php

namespace App\Workflows\Triggers;

class TaskCompletedTrigger extends TaskTrigger
{
    public function key(): string
    {
        return 'task.completed';
    }

    public function label(): string
    {
        return 'Task is completed';
    }

    public function description(): string
    {
        return 'Runs when a task is moved to Done.';
    }
}
