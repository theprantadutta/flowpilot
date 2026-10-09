<?php

namespace App\Workflows\Triggers;

use App\Models\InventoryItem;
use App\Workflows\Fields\Field;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class InventoryLowStockTrigger extends Trigger
{
    public function key(): string
    {
        return 'inventory.low_stock';
    }

    public function label(): string
    {
        return 'Stock runs low';
    }

    public function description(): string
    {
        return 'Runs when an item drops to its reorder point. Use it to reorder or warn the team.';
    }

    public function subjectType(): string
    {
        return 'inventory_item';
    }

    protected function ownFields(array $config): array
    {
        return [
            new Field('subject.name', 'Item name', 'text'),
            new Field('subject.sku', 'SKU', 'text'),
            new Field('subject.category', 'Category', 'text'),
            new Field('subject.current_stock', 'Stock left', 'number'),
            new Field('subject.reorder_point', 'Reorder point', 'number'),
            new Field('subject.minimum_stock', 'Minimum stock', 'number'),
            new Field('subject.reorder_quantity', 'Reorder quantity', 'number'),
            new Field('subject.supplier', 'Supplier', 'text'),
        ];
    }

    public function snapshot(Model $subject): array
    {
        if (! $subject instanceof InventoryItem) {
            throw new InvalidArgumentException('Low stock triggers need an inventory item.');
        }

        $subject->loadMissing(['category:id,organization_id,name', 'supplier:id,organization_id,name']);

        return [
            'id' => $subject->id,
            'reference' => $subject->sku,
            'name' => $subject->name,
            'title' => $subject->name,
            'sku' => $subject->sku,
            'category' => $subject->category?->name,
            'current_stock' => $subject->current_stock,
            'reorder_point' => $subject->reorder_point,
            'minimum_stock' => $subject->minimum_stock,
            'reorder_quantity' => $subject->reorder_quantity,
            'supplier' => $subject->supplier?->name,
            'supplier_id' => $subject->supplier_id,
            'unit_cost' => $subject->unit_cost_amount,
            'unit' => $subject->unit->value,
        ];
    }

    public function subjectLabel(Model $subject): ?string
    {
        return $subject instanceof InventoryItem ? "{$subject->sku} {$subject->name}" : null;
    }
}
