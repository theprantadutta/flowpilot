<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\InventoryItem;

class InventoryLowNotification extends TenantNotification
{
    public string $itemId;

    public string $itemName;

    public string $sku;

    public string $stock;

    public string $reorderPoint;

    public bool $out;

    public function __construct(InventoryItem $item)
    {
        parent::__construct();

        $this->itemId = $item->id;
        $this->itemName = $item->name;
        $this->sku = $item->sku;
        $this->stock = $item->unit->quantity($item->current_stock);
        $this->reorderPoint = $item->unit->quantity($item->reorder_point);
        $this->out = $item->current_stock <= 0;
    }

    public function type(): NotificationType
    {
        return NotificationType::InventoryLow;
    }

    public function title(): string
    {
        return $this->out ? "Out of stock: {$this->itemName}" : "Low stock: {$this->itemName}";
    }

    public function body(): ?string
    {
        return $this->out
            ? "{$this->sku} has run out. Its reorder point is {$this->reorderPoint}."
            : "{$this->sku} is down to {$this->stock}, at or below its reorder point of {$this->reorderPoint}.";
    }

    public function url(): ?string
    {
        return $this->tenantRoute('inventory.items.show', ['item' => $this->itemId]);
    }

    public function tone(): string
    {
        return $this->out ? 'danger' : 'warning';
    }

    public function actionText(): string
    {
        return 'Open item';
    }
}
