<?php

namespace App\Http\Requests\Inventory;

use App\Enums\Permission;
use App\Models\Supplier;
use App\Support\Tenancy\Tenancy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can(Permission::InventoryManage->value);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $supplier = $this->route('supplier');

        return [
            'name' => ['required', 'string', 'min:2', 'max:120', Rule::unique('suppliers', 'name')
                ->where('organization_id', app(Tenancy::class)->currentOrFail()->id)
                ->ignore($supplier instanceof Supplier ? $supplier->id : null)],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'website' => ['nullable', 'url:https,http', 'max:255'],
            'lead_time_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['name.unique' => 'There is already a supplier with this name.'];
    }
}
