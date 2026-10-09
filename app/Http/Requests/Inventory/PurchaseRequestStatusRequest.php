<?php

namespace App\Http\Requests\Inventory;

use App\Enums\PurchaseRequestStatus;
use App\Models\PurchaseRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Deciding by hand, marking ordered, or cancelling a purchase request.
 */
class PurchaseRequestStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $purchase = $this->route('purchaseRequest');

        if (! $purchase instanceof PurchaseRequest) {
            return false;
        }

        return (bool) $this->user()?->can(match ($this->input('status')) {
            'approved', 'rejected' => 'decide',
            'ordered' => 'order',
            default => 'cancel',
        }, $purchase);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['approved', 'rejected', 'ordered', 'cancelled'])],
            'supplier_reference' => ['nullable', 'string', 'max:80'],
        ];
    }

    public function status(): PurchaseRequestStatus
    {
        return PurchaseRequestStatus::from((string) $this->validated('status'));
    }

    public function supplierReference(): ?string
    {
        $reference = $this->validated('supplier_reference');

        return is_string($reference) && trim($reference) !== '' ? trim($reference) : null;
    }
}
