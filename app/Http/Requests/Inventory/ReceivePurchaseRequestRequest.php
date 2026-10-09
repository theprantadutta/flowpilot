<?php

namespace App\Http\Requests\Inventory;

use App\Models\InventoryLocation;
use App\Models\PurchaseRequest;
use App\Rules\BelongsToCurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;

class ReceivePurchaseRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $purchase = $this->route('purchaseRequest');

        return $purchase instanceof PurchaseRequest && (bool) $this->user()?->can('receive', $purchase);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $purchase = $this->route('purchaseRequest');
        $stocked = $purchase instanceof PurchaseRequest && $purchase->inventory_item_id !== null;

        return [
            'quantity' => ['required', 'integer', 'min:1'],
            'location_id' => [$stocked ? 'required' : 'nullable', new BelongsToCurrentOrganization(InventoryLocation::class, 'Choose where it was received.')],
            'request_key' => ['required', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['location_id.required' => 'Choose where it was received.'];
    }
}
