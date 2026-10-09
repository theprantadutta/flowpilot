<?php

namespace App\Workflows\Triggers;

use App\Models\PurchaseRequest;
use App\Workflows\Fields\Field;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class PurchaseRequestSubmittedTrigger extends Trigger
{
    public function key(): string
    {
        return 'purchase_request.submitted';
    }

    public function label(): string
    {
        return 'Purchase request is submitted';
    }

    public function description(): string
    {
        return 'Runs when someone asks to buy something. Use it to route the request for approval.';
    }

    public function subjectType(): string
    {
        return 'purchase_request';
    }

    protected function ownFields(array $config): array
    {
        return [
            new Field('subject.reference', 'Request reference', 'text'),
            new Field('subject.item', 'Item', 'text'),
            new Field('subject.sku', 'Item SKU', 'text'),
            new Field('subject.quantity', 'Quantity', 'number'),
            new Field('subject.unit_cost', 'Unit cost', 'money'),
            new Field('subject.amount', 'Total amount', 'money'),
            new Field('subject.supplier', 'Supplier', 'text'),
            new Field('subject.needed_by', 'Needed by', 'date'),
            new Field('subject.reason', 'Reason', 'text'),
            new Field('subject.requester_id', 'Requested by', 'person'),
        ];
    }

    public function snapshot(Model $subject): array
    {
        if (! $subject instanceof PurchaseRequest) {
            throw new InvalidArgumentException('Purchase request triggers need a purchase request.');
        }

        $subject->loadMissing(['item:id,organization_id,sku', 'supplier:id,organization_id,name']);

        return [
            'id' => $subject->id,
            'reference' => $subject->reference(),
            'item' => $subject->item_name,
            'sku' => $subject->item?->sku,
            'quantity' => $subject->quantity,
            'unit_cost' => $subject->unit_cost_amount,
            'amount' => $subject->total_amount,
            'supplier' => $subject->supplier?->name,
            'needed_by' => $subject->needed_by?->toDateString(),
            'reason' => $subject->reason,
            'requester_id' => $subject->requester_id,
            'status' => $subject->status->value,
        ];
    }

    public function subjectLabel(Model $subject): ?string
    {
        return $subject instanceof PurchaseRequest ? "{$subject->reference()} {$subject->summary()}" : null;
    }
}
