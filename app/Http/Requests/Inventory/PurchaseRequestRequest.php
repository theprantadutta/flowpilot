<?php

namespace App\Http\Requests\Inventory;

use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Rules\BelongsToCurrentOrganization;
use App\Support\Money;
use App\Support\Tenancy\Tenancy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

class PurchaseRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', PurchaseRequest::class);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'inventory_item_id' => ['nullable', new BelongsToCurrentOrganization(InventoryItem::class, 'Choose an item from this organization.')],
            'item_name' => ['required_without:inventory_item_id', 'nullable', 'string', 'min:2', 'max:160'],
            'supplier_id' => ['nullable', new BelongsToCurrentOrganization(Supplier::class, 'Choose a supplier from this organization.')],
            'deliver_to_location_id' => ['nullable', new BelongsToCurrentOrganization(InventoryLocation::class, 'Choose a location from this organization.')],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'unit_cost' => ['required', 'string', 'max:20', 'regex:'.Money::PATTERN],
            'needed_by' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'request_key' => ['required', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'item_name.required_without' => 'Choose a stocked item or describe what you need.',
            'unit_cost.required' => 'Enter what one costs.',
            'unit_cost.regex' => 'Enter a cost, like 12.50.',
            'needed_by.after_or_equal' => 'The date cannot be in the past.',
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
     * @return array{item_name: string, inventory_item_id: string|null, supplier_id: string|null, deliver_to_location_id: string|null, quantity: int, unit_cost_amount: int, needed_by: string|null, reason: string|null, idempotency_key: string}
     */
    public function purchase(): array
    {
        $data = $this->validated();
        $item = is_string($data['inventory_item_id'] ?? null) ? InventoryItem::query()->whereKey($data['inventory_item_id'])->first() : null;

        return [
            'item_name' => $item->name ?? trim((string) $data['item_name']),
            'inventory_item_id' => $item?->id,
            'supplier_id' => $data['supplier_id'] ?? $item?->supplier_id,
            'deliver_to_location_id' => $data['deliver_to_location_id'] ?? $item?->default_location_id,
            'quantity' => (int) $data['quantity'],
            'unit_cost_amount' => Money::toMinorUnits((string) $data['unit_cost'], app(Tenancy::class)->currentOrFail()->currency),
            'needed_by' => $data['needed_by'] ?? null,
            'reason' => $data['reason'] ?? null,
            'idempotency_key' => 'request:'.$data['request_key'],
        ];
    }
}
