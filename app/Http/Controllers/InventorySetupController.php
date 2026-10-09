<?php

namespace App\Http\Controllers;

use App\Actions\Inventory\SaveInventoryRecord;
use App\Enums\Permission;
use App\Http\Requests\Inventory\CategoryRequest;
use App\Http\Requests\Inventory\LocationRequest;
use App\Http\Requests\Inventory\SupplierRequest;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryStockLevel;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Suppliers, locations and categories: the records items point at.
 */
class InventorySetupController extends Controller
{
    public function suppliers(Request $request): Response
    {
        Gate::authorize(Permission::InventoryView->value);

        /** @var User $user */
        $user = $request->user();

        return Inertia::render('inventory/Suppliers', [
            'suppliers' => Supplier::query()
                ->withCount('items')
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get()
                ->map(fn (Supplier $supplier): array => [
                    'id' => $supplier->id,
                    'name' => $supplier->name,
                    'contact_name' => $supplier->contact_name,
                    'email' => $supplier->email,
                    'phone' => $supplier->phone,
                    'website' => $supplier->website,
                    'lead_time_days' => $supplier->lead_time_days,
                    'notes' => $supplier->notes,
                    'is_active' => $supplier->is_active,
                    'items_count' => (int) $supplier->getAttribute('items_count'),
                ]),
            'can' => ['manage' => $user->can(Permission::InventoryManage->value)],
        ]);
    }

    public function storeSupplier(SupplierRequest $request, SaveInventoryRecord $save): RedirectResponse
    {
        return $this->save($request, new Supplier, $save);
    }

    public function updateSupplier(SupplierRequest $request, Supplier $supplier, SaveInventoryRecord $save): RedirectResponse
    {
        return $this->save($request, $supplier, $save);
    }

    public function locations(Request $request): Response
    {
        Gate::authorize(Permission::InventoryView->value);

        /** @var User $user */
        $user = $request->user();

        $totals = InventoryStockLevel::query()
            ->selectRaw('inventory_location_id, count(*) as items, sum(quantity) as units')
            ->where('quantity', '>', 0)
            ->groupBy('inventory_location_id')
            ->get()
            ->keyBy('inventory_location_id');

        return Inertia::render('inventory/Locations', [
            'locations' => InventoryLocation::query()
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get()
                ->map(fn (InventoryLocation $location): array => [
                    'id' => $location->id,
                    'name' => $location->name,
                    'code' => $location->code,
                    'description' => $location->description,
                    'is_active' => $location->is_active,
                    'items' => (int) ($totals->get($location->id)?->getAttribute('items') ?? 0),
                    'units' => (int) ($totals->get($location->id)?->getAttribute('units') ?? 0),
                ]),
            'categories' => InventoryCategory::query()
                ->withCount('items')
                ->orderBy('name')
                ->get()
                ->map(fn (InventoryCategory $category): array => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'items_count' => (int) $category->getAttribute('items_count'),
                ]),
            'can' => ['manage' => $user->can(Permission::InventoryManage->value)],
        ]);
    }

    public function storeLocation(LocationRequest $request, SaveInventoryRecord $save): RedirectResponse
    {
        return $this->save($request, new InventoryLocation, $save);
    }

    public function updateLocation(LocationRequest $request, InventoryLocation $location, SaveInventoryRecord $save): RedirectResponse
    {
        return $this->save($request, $location, $save);
    }

    public function storeCategory(CategoryRequest $request, SaveInventoryRecord $save): RedirectResponse
    {
        return $this->save($request, new InventoryCategory, $save);
    }

    public function updateCategory(CategoryRequest $request, InventoryCategory $category, SaveInventoryRecord $save): RedirectResponse
    {
        return $this->save($request, $category, $save);
    }

    /**
     * Categories are labels: deleting one leaves its items uncategorised.
     */
    public function destroyCategory(InventoryCategory $category): RedirectResponse
    {
        Gate::authorize(Permission::InventoryManage->value);

        InventoryItem::query()->where('category_id', $category->id)->update(['category_id' => null]);
        $category->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$category->name} removed."]);

        return back();
    }

    private function save(FormRequest $request, Supplier|InventoryLocation|InventoryCategory $record, SaveInventoryRecord $save): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var array<string, mixed> $attributes */
        $attributes = $request->validated();

        $save->handle($record, $user, $attributes);

        return back();
    }
}
