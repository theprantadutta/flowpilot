<?php

namespace App\Support\Search\Sources;

use App\Enums\Permission;
use App\Models\InventoryItem;
use App\Models\Organization;
use App\Support\Search\Contains;
use App\Support\Search\SearchResult;
use App\Support\Search\SearchSource;

class InventorySearchSource implements SearchSource
{
    public function permission(): Permission
    {
        return Permission::InventoryView;
    }

    public function search(Organization $organization, string $term, int $limit): array
    {
        return array_values(InventoryItem::query()
            ->where('is_active', true)
            ->where(fn ($query) => Contains::any($query, ['name', 'sku'], $term))
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (InventoryItem $item): SearchResult => new SearchResult(
                group: 'Inventory',
                id: $item->id,
                title: "{$item->sku} {$item->name}",
                subtitle: "{$item->stockStatus()->label()} · {$item->unit->quantity($item->current_stock)} on hand",
                url: route('inventory.items.show', ['organization' => $organization->slug, 'item' => $item->id]),
                icon: 'package',
            ))
            ->all());
    }
}
