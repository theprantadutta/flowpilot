<?php

namespace App\Http\Requests\Workflows;

use Illuminate\Foundation\Http\FormRequest;

class StartWorkflowRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('execute', $this->route('workflow'));
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'input' => ['present', 'array', 'max:20'],
            'request_key' => ['required', 'uuid'],
        ];
    }
}
