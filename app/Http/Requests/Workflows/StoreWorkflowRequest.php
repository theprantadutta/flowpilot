<?php

namespace App\Http\Requests\Workflows;

use App\Models\Workflow;
use App\Workflows\Templates\WorkflowTemplates;
use App\Workflows\Triggers\TriggerRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Workflow::class);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'template' => ['nullable', 'string', Rule::in(array_column(app(WorkflowTemplates::class)->all(), 'key'))],
            'trigger' => ['required_without:template', 'nullable', 'string', Rule::in(app(TriggerRegistry::class)->keys())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Give the workflow a name.',
            'trigger.required_without' => 'Choose what starts the workflow.',
        ];
    }
}
