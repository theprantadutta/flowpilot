<?php

namespace App\Http\Requests\Billing;

use App\Enums\Permission;
use App\Enums\Plan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RequestPlanChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::BillingManage->value) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'plan' => ['required', Rule::enum(Plan::class)->except([Plan::Free])],
            'message' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function plan(): Plan
    {
        return Plan::from((string) $this->validated('plan'));
    }
}
