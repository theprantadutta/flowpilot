<?php

namespace App\Http\Requests\Inventory;

use App\Enums\InventoryUnit;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\Supplier;
use App\Rules\BelongsToCurrentOrganization;
use App\Support\Money;
use App\Support\Tenancy\Tenancy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

/**
 * Creating and editing stock items. Edits may send any subset of fields.
 */
class InventoryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $item = $this->route('item');

        return $item instanceof InventoryItem
            ? (bool) $this->user()?->can('update', $item)
            : (bool) $this->user()?->can('create', InventoryItem::class);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('sku'))) {
            $this->merge(['sku' => strtoupper(trim($this->input('sku')))]);
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $item = $this->route('item');
        $required = $item instanceof InventoryItem ? 'sometimes' : 'required';
        $organizationId = app(Tenancy::class)->currentOrFail()->id;

        return [
            'sku' => [$required, 'string', 'max:60', 'regex:/^[A-Z0-9][A-Z0-9._\-\/]*$/', Rule::unique('inventory_items', 'sku')
                ->where('organization_id', $organizationId)
                ->ignore($item instanceof InventoryItem ? $item->id : null)],
            'name' => [$required, 'string', 'min:2', 'max:160'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'category_id' => ['sometimes', 'nullable', new BelongsToCurrentOrganization(InventoryCategory::class, 'Choose a category from this organization.')],
            'supplier_id' => ['sometimes', 'nullable', new BelongsToCurrentOrganization(Supplier::class, 'Choose a supplier from this organization.')],
            'default_location_id' => ['sometimes', 'nullable', new BelongsToCurrentOrganization(InventoryLocation::class, 'Choose a location from this organization.')],
            'unit' => [$required, Rule::enum(InventoryUnit::class)],
            'minimum_stock' => ['sometimes', 'integer', 'min:0', 'max:10000000'],
            'reorder_point' => ['sometimes', 'integer', 'min:0', 'max:10000000'],
            'reorder_quantity' => ['sometimes', 'integer', 'min:0', 'max:10000000'],
            'unit_cost' => ['sometimes', 'nullable', 'string', 'max:20', 'regex:'.Money::PATTERN],
            'is_active' => ['sometimes', 'boolean'],
            'opening_stock' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:10000000'],
            'opening_location_id' => ['nullable', 'required_with_all:opening_stock', new BelongsToCurrentOrganization(InventoryLocation::class, 'Choose where the opening stock is.')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sku.unique' => 'Another item already uses this SKU.',
            'sku.regex' => 'Use letters, numbers, dashes, dots and slashes for the SKU.',
            'unit_cost.regex' => 'Enter a cost, like 12.50.',
            'opening_location_id.required_with_all' => 'Choose where the opening stock is.',
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $cost = $this->input('unit_cost');

                if (is_string($cost) && $cost !== '') {
                    try {
                        Money::toMinorUnits($cost, app(Tenancy::class)->currentOrFail()->currency);
                    } catch (InvalidArgumentException $exception) {
                        $validator->errors()->add('unit_cost', $exception->getMessage());
                    }
                }
            },
        ];
    }

    /**
     * Item attributes, with the cost in minor units.
     *
     * @return array<string, mixed>
     */
    public function itemAttributes(): array
    {
        $attributes = collect($this->validated())->except(['unit_cost', 'opening_stock', 'opening_location_id'])->all();

        if ($this->has('unit_cost')) {
            $cost = $this->validated('unit_cost');
            $attributes['unit_cost_amount'] = is_string($cost) && $cost !== ''
                ? Money::toMinorUnits($cost, app(Tenancy::class)->currentOrFail()->currency)
                : null;
        }

        return $attributes;
    }

    public function openingStock(): int
    {
        return (int) ($this->validated('opening_stock') ?? 0);
    }

    public function openingLocation(): ?string
    {
        $location = $this->validated('opening_location_id');

        return is_string($location) ? $location : null;
    }
}
