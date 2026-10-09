<?php

namespace App\Http\Requests\Billing;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBillingDetailsRequest extends FormRequest
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
            'legal_name' => ['nullable', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'country' => ['nullable', 'string', 'size:2', 'alpha'],
            'tax_id' => ['nullable', 'string', 'max:40'],
        ];
    }

    /**
     * @return array{legal_name: string|null, email: string|null, address: string|null, country: string|null, tax_id: string|null}
     */
    public function details(): array
    {
        $country = $this->validated('country');

        return [
            'legal_name' => $this->validated('legal_name'),
            'email' => $this->validated('email'),
            'address' => $this->validated('address'),
            'country' => is_string($country) ? strtoupper($country) : null,
            'tax_id' => $this->validated('tax_id'),
        ];
    }
}
