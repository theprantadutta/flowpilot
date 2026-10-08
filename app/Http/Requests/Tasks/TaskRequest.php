<?php

namespace App\Http\Requests\Tasks;

use App\Enums\Priority;
use App\Enums\TaskStatus;
use App\Http\Requests\Concerns\NormalizesTags;
use App\Models\Project;
use App\Rules\ActiveMember;
use App\Rules\BelongsToCurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared validation for creating and editing tasks. Edits may send any subset
 * of fields (inline edits change one thing at a time).
 */
abstract class TaskRequest extends FormRequest
{
    use NormalizesTags;

    protected function prepareForValidation(): void
    {
        $this->normalizeTags();
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'title' => [$required, 'string', 'min:2', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'status' => ['sometimes', Rule::enum(TaskStatus::class)],
            'priority' => ['sometimes', Rule::enum(Priority::class)],
            'project_id' => ['sometimes', 'nullable', new BelongsToCurrentOrganization(Project::class, 'Choose a project from this organization.')],
            'assignee_id' => ['sometimes', 'nullable', 'integer', new ActiveMember('Assign the task to an active member of this organization.')],
            'due_date' => ['sometimes', 'nullable', 'date'],
            ...$this->tagRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Give the task a title.',
            'title.min' => 'Use at least two characters for the title.',
        ];
    }
}
