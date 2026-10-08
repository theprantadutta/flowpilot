<?php

namespace App\Http\Requests\Issues;

use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Http\Requests\Concerns\NormalizesTags;
use App\Models\Project;
use App\Rules\ActiveMember;
use App\Rules\BelongsToCurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class IssueRequest extends FormRequest
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
            'severity' => [$required, Rule::enum(IssueSeverity::class)],
            'status' => ['sometimes', Rule::enum(IssueStatus::class)],
            'project_id' => ['sometimes', 'nullable', new BelongsToCurrentOrganization(Project::class, 'Choose a project from this organization.')],
            'assignee_id' => ['sometimes', 'nullable', 'integer', new ActiveMember('Assign the issue to an active member of this organization.')],
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
            'title.required' => 'Describe the issue in a few words.',
            'severity.required' => 'Choose how severe the issue is.',
        ];
    }
}
