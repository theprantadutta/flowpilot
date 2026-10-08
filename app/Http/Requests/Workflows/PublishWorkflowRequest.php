<?php

namespace App\Http\Requests\Workflows;

use Illuminate\Foundation\Http\FormRequest;

class PublishWorkflowRequest extends FormRequest
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
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
