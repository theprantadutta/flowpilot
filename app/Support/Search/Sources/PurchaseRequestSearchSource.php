<?php

namespace App\Support\Search\Sources;

use App\Enums\Permission;
use App\Models\Organization;
use App\Models\PurchaseRequest;
use App\Support\Search\Contains;
use App\Support\Search\SearchResult;
use App\Support\Search\SearchSource;
use Illuminate\Database\Eloquent\Builder;

class PurchaseRequestSearchSource implements SearchSource
{
    public function permission(): Permission
    {
        return Permission::InventoryView;
    }

    public function search(Organization $organization, string $term, int $limit): array
    {
        $number = ltrim(strtoupper(trim($term)), 'PR-');

        return array_values(PurchaseRequest::query()
            ->where(fn (Builder $query) => ctype_digit($number)
                ? $query->where('number', (int) $number)->orWhere(fn (Builder $q) => Contains::any($q, ['item_name'], $term))
                : Contains::any($query, ['item_name'], $term))
            ->orderByRaw("case when status in ('submitted', 'approved', 'ordered') then 0 else 1 end")
            ->orderByDesc('number')
            ->limit($limit)
            ->get()
            ->map(fn (PurchaseRequest $request): SearchResult => new SearchResult(
                group: 'Purchase requests',
                id: $request->id,
                title: "{$request->reference()} {$request->summary()}",
                subtitle: $request->status->label(),
                url: route('purchase-requests.show', ['organization' => $organization->slug, 'purchaseRequest' => $request->id]),
                icon: 'truck',
            ))
            ->all());
    }
}
