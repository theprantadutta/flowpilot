<?php

namespace App\Http\Requests\Workflows;

use App\Enums\WorkflowStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkflowStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('publish', $this->route('workflow'));
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(WorkflowStatus::class)->only([WorkflowStatus::Active, WorkflowStatus::Paused, WorkflowStatus::Archived])],
        ];
    }

    public function status(): WorkflowStatus
    {
        return WorkflowStatus::from((string) $this->validated('status'));
    }
}
