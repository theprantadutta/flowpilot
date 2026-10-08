<?php

namespace App\Http\Requests\Projects;

use App\Enums\Priority;
use App\Enums\ProjectStatus;
use App\Http\Requests\Concerns\NormalizesTags;
use App\Rules\ActiveMember;
use App\Support\Money;
use App\Support\Tenancy\Tenancy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Shared validation for creating and editing projects.
 */
abstract class ProjectRequest extends FormRequest
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
            'name' => [$required, 'string', 'min:2', 'max:160'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'status' => ['sometimes', Rule::enum(ProjectStatus::class)],
            'priority' => ['sometimes', Rule::enum(Priority::class)],
            'owner_id' => ['sometimes', 'nullable', 'integer', new ActiveMember],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'due_date' => ['sometimes', 'nullable', 'date', Rule::when($this->filled('start_date'), 'after_or_equal:start_date')],
            'budget' => ['sometimes', 'nullable', 'string', 'max:24', 'regex:'.Money::PATTERN],
            'member_ids' => ['sometimes', 'array', 'max:200'],
            'member_ids.*' => ['integer', 'distinct', new ActiveMember],
            ...$this->tagRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Give the project a name.',
            'due_date.after_or_equal' => 'The due date cannot be before the start date.',
            'budget.regex' => 'Enter the budget as a number, for example 25000 or 25,000.00.',
        ];
    }

    /**
     * Validated attributes ready for the model, with the budget in minor units.
     *
     * @return array<string, mixed>
     */
    public function projectAttributes(): array
    {
        $attributes = $this->safe()->except(['budget', 'member_ids']);

        if ($this->has('budget')) {
            $currency = app(Tenancy::class)->currentOrFail()->currency;
            $budget = $this->input('budget');

            try {
                $attributes['budget_amount'] = is_string($budget) && $budget !== ''
                    ? Money::toMinorUnits($budget, $currency)
                    : null;
            } catch (InvalidArgumentException $exception) {
                throw ValidationException::withMessages(['budget' => $exception->getMessage()]);
            }

            $attributes['budget_currency'] = $attributes['budget_amount'] === null ? null : $currency;
        }

        return $attributes;
    }

    /**
     * @return list<int>|null
     */
    public function memberIds(): ?array
    {
        if (! $this->has('member_ids')) {
            return null;
        }

        return array_values(array_map(intval(...), (array) $this->validated('member_ids', [])));
    }
}
