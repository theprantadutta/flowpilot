<?php

namespace App\Http\Requests\Workflows;

use App\Workflows\Definition\DefinitionValidator;
use App\Workflows\Definition\WorkflowDefinition;
use App\Workflows\Triggers\TriggerRegistry;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class SaveWorkflowDraftRequest extends FormRequest
{
    /**
     * Largest canvas accepted, in bytes of JSON.
     */
    public const int MAX_BYTES = 512 * 1024;

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('update', $this->route('workflow'));
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'trigger' => ['required', 'string', Rule::in(app(TriggerRegistry::class)->keys())],
            'definition' => ['required', 'array', function (string $attribute, mixed $value, Closure $fail): void {
                if (strlen((string) json_encode($value)) > self::MAX_BYTES) {
                    $fail('The workflow is too large to save.');
                }
            }],
            'definition.nodes' => ['present', 'array', 'max:'.WorkflowDefinition::MAX_NODES],
            'definition.nodes.*.id' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{1,64}$/'],
            'definition.nodes.*.type' => ['required', 'string', 'max:30'],
            'definition.nodes.*.position' => ['nullable', 'array'],
            'definition.nodes.*.data' => ['nullable', 'array'],
            'definition.nodes.*.data.label' => ['nullable', 'string', 'max:120'],
            'definition.nodes.*.data.config' => ['nullable', 'array'],
            'definition.edges' => ['present', 'array', 'max:'.DefinitionValidator::MAX_EDGES],
            'definition.edges.*.source' => ['required', 'string', 'max:64'],
            'definition.edges.*.target' => ['required', 'string', 'max:64'],
            'definition.edges.*.sourceHandle' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function definition(): WorkflowDefinition
    {
        try {
            return WorkflowDefinition::fromArray((array) $this->validated('definition'));
        } catch (InvalidArgumentException $exception) {
            abort(422, $exception->getMessage());
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'definition.nodes.max' => 'A workflow can have at most '.WorkflowDefinition::MAX_NODES.' steps.',
            'definition.edges.max' => 'A workflow can have at most '.DefinitionValidator::MAX_EDGES.' connections.',
        ];
    }
}
