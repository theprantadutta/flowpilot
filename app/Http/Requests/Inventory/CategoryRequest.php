<?php

namespace App\Http\Requests\Inventory;

use App\Enums\Permission;
use App\Models\InventoryCategory;
use App\Support\Tenancy\Tenancy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
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
        $category = $this->route('category');

        return [
            'name' => ['required', 'string', 'min:2', 'max:80', Rule::unique('inventory_categories', 'name')
                ->where('organization_id', app(Tenancy::class)->currentOrFail()->id)
                ->ignore($category instanceof InventoryCategory ? $category->id : null)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['name.unique' => 'There is already a category with this name.'];
    }
}
