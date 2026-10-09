<?php

namespace App\Http\Requests\Platform;

use App\Enums\Plan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExtendTrialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isPlatformAdmin() ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'days' => ['required', 'integer', 'min:1', 'max:90'],
            'plan' => ['required', Rule::enum(Plan::class)->except([Plan::Free])],
        ];
    }

    public function plan(): Plan
    {
        return Plan::from((string) $this->validated('plan'));
    }
}
