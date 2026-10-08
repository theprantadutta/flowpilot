<?php

namespace App\Http\Requests\Tasks;

use App\Models\Task;

class UpdateTaskRequest extends TaskRequest
{
    public function authorize(): bool
    {
        $task = $this->route('task');

        return $task instanceof Task && (bool) $this->user()?->can('update', $task);
    }
}
