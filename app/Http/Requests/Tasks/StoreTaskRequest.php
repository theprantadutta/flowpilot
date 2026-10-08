<?php

namespace App\Http\Requests\Tasks;

use App\Models\Task;

class StoreTaskRequest extends TaskRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Task::class);
    }
}
