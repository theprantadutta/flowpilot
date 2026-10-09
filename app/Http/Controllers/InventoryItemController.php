<?php

namespace App\Http\Controllers;

use App\Actions\Inventory\CreateInventoryItem;
use App\Actions\Inventory\UpdateInventoryItem;
use App\Enums\InventoryUnit;
use App\Enums\MovementType;
use App\Enums\PurchaseRequestStatus;
use App\Enums\StockStatus;
use App\Http\Requests\Inventory\InventoryItemRequest;
use App\Http\Resources\InventoryItemResource;
use App\Http\Resources\InventoryMovementResource;
use App\Http\Resources\PurchaseRequestResource;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Money;
use App\Support\Search\Contains;
use App\Support\Tenancy\Tenancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class InventoryItemController extends Controller
{
    public function __construct(private readonly Tenancy $tenancy) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', InventoryItem::class);

        /** @var User $user */
        $user = $request->user();
        $organization = $this->tenancy->currentOrFail();

        $filters = [
            'q' => $request->string('q')->trim()->limit(80, '')->toString(),
            'stock' => in_array($request->query('stock'), ['low', 'out', 'archived'], true) ? (string) $request->query('stock') : null,
            'category' => Str::isUuid($request->string('category')->toString()) ? $request->string('category')->toString() : null,
            'supplier' => Str::isUuid($request->string('supplier')->toString()) ? $request->string('supplier')->toString() : null,
        ];

        $items = InventoryItem::query()
            ->with(['category:id,organization_id,name', 'supplier:id,organization_id,name', 'defaultLocation:id,organization_id,name'])
            ->where('is_active', $filters['stock'] !== 'archived')
            ->when($filters['q'] !== '', fn (Builder $query) => Contains::any($query, ['name', 'sku'], $filters['q']))
            ->when($filters['stock'] === 'low', fn (Builder $query) => $query->low()->where('current_stock', '>', 0))
            ->when($filters['stock'] === 'out', fn (Builder $query) => $query->where('current_stock', '<=', 0))
            ->when($filters['category'], fn (Builder $query, string $id) => $query->where('category_id', $id))
            ->when($filters['supplier'], fn (Builder $query, string $id) => $query->where('supplier_id', $id))
            // Items needing attention first.
            ->orderByRaw('case when current_stock <= 0 then 0 when current_stock <= reorder_point then 1 else 2 end')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $active = InventoryItem::query()->where('is_active', true);
        $value = (int) (clone $active)->whereNotNull('unit_cost_amount')->selectRaw('sum(current_stock * unit_cost_amount) as value')->value('value');

        return Inertia::render('inventory/Index', [
            'items' => InventoryItemResource::collection($items),
            'filters' => $filters,
            'stats' => [
                'items' => (clone $active)->count(),
                'low' => (clone $active)->low()->where('current_stock', '>', 0)->count(),
                'out' => (clone $active)->where('current_stock', '<=', 0)->count(),
                'value' => Money::format($value, $organization->currency, $organization->locale),
            ],
            'options' => fn () => $this->options(),
            'can' => [
                'manage' => $user->can('create', InventoryItem::class),
                'request' => $user->can('create', PurchaseRequest::class),
            ],
        ]);
    }

    public function store(InventoryItemRequest $request, CreateInventoryItem $createItem): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $item = $createItem->handle($user, $request->itemAttributes(), $request->openingStock(), $request->openingLocation());

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$item->name} added."]);

        return to_route('inventory.items.show', ['item' => $item->id]);
    }

    public function show(Request $request, InventoryItem $item): Response
    {
        Gate::authorize('view', $item);

        /** @var User $user */
        $user = $request->user();

        $item->load([
            'category:id,organization_id,name',
            'supplier:id,organization_id,name',
            'defaultLocation:id,organization_id,name',
            'stockLevels.location:id,organization_id,name',
        ]);

        return Inertia::render('inventory/Show', [
            'item' => (new InventoryItemResource($item))->resolve($request),
            'movements' => Inertia::defer(fn () => InventoryMovementResource::collection(
                $item->movements()
                    ->with(['item:id,organization_id,sku,name,unit', 'fromLocation:id,organization_id,name', 'toLocation:id,organization_id,name', 'performer:id,name,avatar_path'])
                    ->latest('occurred_at')
                    ->orderByDesc('number')
                    ->limit(50)
                    ->get(),
            )),
            'purchaseRequests' => fn () => PurchaseRequestResource::collection(
                PurchaseRequest::query()
                    ->where('inventory_item_id', $item->id)
                    ->whereIn('status', [PurchaseRequestStatus::Submitted->value, PurchaseRequestStatus::Approved->value, PurchaseRequestStatus::Ordered->value])
                    ->with('requester:id,name,avatar_path')
                    ->latest()
                    ->limit(10)
                    ->get(),
            ),
            'movementTypes' => MovementType::options(),
            'options' => fn () => $this->options(),
            'can' => [
                'update' => $user->can('update', $item),
                'move' => $user->can('move', $item),
                'request' => $user->can('create', PurchaseRequest::class),
            ],
        ]);
    }

    public function update(InventoryItemRequest $request, InventoryItem $item, UpdateInventoryItem $updateItem): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $updateItem->handle($item, $user, $request->itemAttributes());

        return back();
    }

    /**
     * Choices for the item and movement forms.
     *
     * @return array<string, mixed>
     */
    private function options(): array
    {
        return [
            'units' => InventoryUnit::options(),
            'statuses' => StockStatus::options(),
            'categories' => InventoryCategory::query()->orderBy('name')->get(['id', 'organization_id', 'name'])
                ->map(fn (InventoryCategory $category): array => ['id' => $category->id, 'name' => $category->name]),
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'organization_id', 'name'])
                ->map(fn (Supplier $supplier): array => ['id' => $supplier->id, 'name' => $supplier->name]),
            'locations' => InventoryLocation::query()->where('is_active', true)->orderBy('name')->get(['id', 'organization_id', 'name'])
                ->map(fn (InventoryLocation $location): array => ['id' => $location->id, 'name' => $location->name]),
        ];
    }
}
