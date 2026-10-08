<?php

namespace App\Http\Requests\Tasks;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Rules\BelongsToCurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A drop on the board: the column, and the cards it landed between.
 */
class MoveTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $task = $this->route('task');

        return $task instanceof Task && (bool) $this->user()?->can('update', $task);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(TaskStatus::class)],
            'after_id' => ['nullable', 'different:before_id', new BelongsToCurrentOrganization(Task::class)],
            'before_id' => ['nullable', new BelongsToCurrentOrganization(Task::class)],
        ];
    }

    public function status(): TaskStatus
    {
        return TaskStatus::from($this->string('status')->toString());
    }

    public function neighbour(string $key): ?Task
    {
        $id = $this->validated($key);

        return is_string($id) ? Task::query()->find($id) : null;
    }
}
