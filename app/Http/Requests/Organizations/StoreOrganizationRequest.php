<?php

namespace App\Http\Requests\Organizations;

use App\Enums\CompanySize;
use App\Enums\Industry;
use App\Enums\UseCase;
use App\Support\Currencies;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\In;

class StoreOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<ValidationRule|Enum|In|string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'industry' => ['required', Rule::enum(Industry::class)],
            'company_size' => ['required', Rule::enum(CompanySize::class)],
            'primary_use_case' => ['required', Rule::enum(UseCase::class)],
            'timezone' => ['nullable', 'string', 'timezone:all'],
            'currency' => ['nullable', 'string', Rule::in(Currencies::codes())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Give your organization a name.',
            'industry.required' => 'Choose the industry that fits best.',
            'company_size.required' => 'Choose a company size.',
            'primary_use_case.required' => 'Choose what you want to use FlowPilot for first.',
        ];
    }

    /**
     * The validated answers, shaped for CreateOrganization.
     *
     * @return array{name: string, industry: string, company_size: string, primary_use_case: string, timezone: string|null, currency: string|null}
     */
    public function organizationAttributes(): array
    {
        return [
            'name' => $this->string('name')->trim()->toString(),
            'industry' => $this->string('industry')->toString(),
            'company_size' => $this->string('company_size')->toString(),
            'primary_use_case' => $this->string('primary_use_case')->toString(),
            'timezone' => $this->filled('timezone') ? $this->string('timezone')->toString() : null,
            'currency' => $this->filled('currency') ? $this->string('currency')->toString() : null,
        ];
    }
}
