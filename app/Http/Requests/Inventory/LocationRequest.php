<?php

namespace App\Http\Requests\Inventory;

use App\Enums\Permission;
use App\Models\InventoryLocation;
use App\Support\Tenancy\Tenancy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LocationRequest extends FormRequest
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
        $location = $this->route('location');

        return [
            'name' => ['required', 'string', 'min:2', 'max:80', Rule::unique('inventory_locations', 'name')
                ->where('organization_id', app(Tenancy::class)->currentOrFail()->id)
                ->ignore($location instanceof InventoryLocation ? $location->id : null)],
            'code' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['name.unique' => 'There is already a location with this name.'];
    }
}
