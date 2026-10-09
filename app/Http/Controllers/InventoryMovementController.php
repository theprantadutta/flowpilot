<?php

namespace App\Http\Controllers;

use App\Actions\Inventory\RecordMovement;
use App\Enums\MovementType;
use App\Http\Requests\Inventory\RecordMovementRequest;
use App\Http\Resources\InventoryMovementResource;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class InventoryMovementController extends Controller
{
    /**
     * The stock ledger: every receipt, issue, count and move, newest first.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', InventoryItem::class);

        $type = MovementType::tryFrom($request->string('type')->toString());
        $location = Str::isUuid($request->string('location')->toString()) ? $request->string('location')->toString() : null;

        $movements = InventoryMovement::query()
            ->with(['item:id,organization_id,sku,name,unit', 'fromLocation:id,organization_id,name', 'toLocation:id,organization_id,name', 'performer:id,name,avatar_path'])
            ->when($type, fn (Builder $query, MovementType $type) => $query->where('type', $type->value))
            ->when($location, fn (Builder $query, string $id) => $query->where(fn (Builder $query) => $query->where('from_location_id', $id)->orWhere('to_location_id', $id)))
            ->latest('occurred_at')
            ->orderByDesc('number')
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('inventory/Movements', [
            'movements' => InventoryMovementResource::collection($movements),
            'filters' => ['type' => $type?->value, 'location' => $location],
            'types' => MovementType::options(),
            'locations' => fn () => InventoryLocation::query()->orderBy('name')->get(['id', 'organization_id', 'name'])
                ->map(fn (InventoryLocation $location): array => ['id' => $location->id, 'name' => $location->name]),
        ]);
    }

    public function store(RecordMovementRequest $request, InventoryItem $item, RecordMovement $recordMovement): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $movement = $recordMovement->handle($item, $user, $request->type(), $request->movement());

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$movement->reference()} recorded. {$item->name} now has {$item->refresh()->unit->quantity($item->current_stock)}."]);

        return back();
    }
}
