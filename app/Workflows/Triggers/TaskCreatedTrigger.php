<?php

namespace App\Workflows\Triggers;

class TaskCreatedTrigger extends TaskTrigger
{
    public function key(): string
    {
        return 'task.created';
    }

    public function label(): string
    {
        return 'Task is created';
    }

    public function description(): string
    {
        return 'Runs whenever someone adds a task.';
    }
}
