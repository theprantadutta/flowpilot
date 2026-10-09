<?php

namespace App\Http\Requests\Platform;

use App\Enums\Limit;
use App\Enums\Plan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Platform administrators only reach this through EnsurePlatformAdmin.
 */
class ChangeOrganizationPlanRequest extends FormRequest
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
        $rules = [
            'plan' => ['required', Rule::enum(Plan::class)],
            'note' => ['nullable', 'string', 'max:500'],
            'limits' => ['nullable', 'array'],
        ];

        foreach (Limit::cases() as $limit) {
            $rules["limits.{$limit->value}"] = ['nullable', 'integer', 'min:0', 'max:100000000'];
        }

        return $rules;
    }

    public function plan(): Plan
    {
        return Plan::from((string) $this->validated('plan'));
    }

    /**
     * Custom limits for Enterprise; an empty field means unlimited.
     *
     * @return array<string, int|null>|null
     */
    public function limitOverrides(): ?array
    {
        if ($this->plan() !== Plan::Enterprise) {
            return null;
        }

        /** @var array<string, int|string|null> $limits */
        $limits = $this->validated('limits') ?? [];
        $overrides = [];

        foreach (Limit::cases() as $limit) {
            $value = $limits[$limit->value] ?? null;
            $overrides[$limit->value] = $value === null || $value === '' ? null : (int) $value;
        }

        return $overrides;
    }
}
