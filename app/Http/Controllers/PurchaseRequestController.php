<?php

namespace App\Http\Controllers;

use App\Actions\Inventory\ChangePurchaseRequestStatus;
use App\Actions\Inventory\ReceivePurchaseRequest;
use App\Actions\Inventory\SubmitPurchaseRequest;
use App\Enums\Permission;
use App\Enums\PurchaseRequestStatus;
use App\Http\Requests\Inventory\PurchaseRequestRequest;
use App\Http\Requests\Inventory\PurchaseRequestStatusRequest;
use App\Http\Requests\Inventory\ReceivePurchaseRequestRequest;
use App\Http\Resources\ApprovalResource;
use App\Http\Resources\InventoryMovementResource;
use App\Http\Resources\PurchaseRequestResource;
use App\Http\Resources\WorkflowRunResource;
use App\Models\ActivityLog;
use App\Models\Approval;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\User;
use App\Models\WorkflowRun;
use App\Support\Activity\ActivityPresenter;
use App\Support\Money;
use App\Support\Search\Contains;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseRequestController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', PurchaseRequest::class);

        /** @var User $user */
        $user = $request->user();
        $seeAll = $user->can(Permission::InventoryView->value);
        $view = $request->query('view') === 'mine' || ! $seeAll ? 'mine' : 'all';
        $status = PurchaseRequestStatus::tryFrom($request->string('status')->toString());
        $q = $request->string('q')->trim()->limit(80, '')->toString();

        $requests = PurchaseRequest::query()
            ->with(['requester:id,name,avatar_path', 'supplier:id,organization_id,name'])
            ->when($view === 'mine', fn (Builder $query) => $query->where('requester_id', $user->id))
            ->when($status, fn (Builder $query, PurchaseRequestStatus $status) => $query->where('status', $status->value))
            ->when($q !== '', function (Builder $query) use ($q): void {
                $number = ltrim(strtoupper($q), 'PR-');

                $query->where(fn (Builder $query) => ctype_digit($number)
                    ? $query->where('number', (int) $number)->orWhere(fn (Builder $q2) => Contains::any($q2, ['item_name'], $q))
                    : Contains::any($query, ['item_name', 'reason'], $q));
            })
            ->orderByRaw("case status when 'submitted' then 0 when 'approved' then 1 when 'ordered' then 2 else 3 end")
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('inventory/PurchaseRequests', [
            'requests' => PurchaseRequestResource::collection($requests),
            'filters' => ['view' => $view, 'status' => $status?->value, 'q' => $q],
            'statuses' => PurchaseRequestStatus::options(),
            'options' => fn () => [
                'items' => InventoryItem::query()->where('is_active', true)->orderBy('name')
                    ->get(['id', 'organization_id', 'sku', 'name', 'unit_cost_amount', 'currency', 'supplier_id', 'reorder_quantity'])
                    ->map(fn (InventoryItem $item): array => [
                        'id' => $item->id,
                        'label' => "{$item->sku} {$item->name}",
                        'unit_cost' => $item->unit_cost_amount !== null && $item->currency !== null ? Money::toDecimalString($item->unit_cost_amount, $item->currency) : null,
                        'supplier_id' => $item->supplier_id,
                        'reorder_quantity' => $item->reorder_quantity,
                    ]),
                'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'organization_id', 'name'])
                    ->map(fn (Supplier $supplier): array => ['id' => $supplier->id, 'name' => $supplier->name]),
                'locations' => InventoryLocation::query()->where('is_active', true)->orderBy('name')->get(['id', 'organization_id', 'name'])
                    ->map(fn (InventoryLocation $location): array => ['id' => $location->id, 'name' => $location->name]),
            ],
            'can' => ['create' => $user->can('create', PurchaseRequest::class), 'seeAll' => $seeAll],
        ]);
    }

    public function store(PurchaseRequestRequest $request, SubmitPurchaseRequest $submit): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $purchase = $submit->handle($user, $request->purchase());

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$purchase->reference()} submitted for approval."]);

        return to_route('purchase-requests.show', ['purchaseRequest' => $purchase->id]);
    }

    public function show(Request $request, PurchaseRequest $purchaseRequest, ActivityPresenter $presenter, SubmitPurchaseRequest $submit): Response
    {
        Gate::authorize('view', $purchaseRequest);

        /** @var User $user */
        $user = $request->user();

        $purchaseRequest->load([
            'requester:id,name,avatar_path',
            'decider:id,name,avatar_path',
            'item:id,organization_id,sku,name,unit,current_stock',
            'supplier:id,organization_id,name',
            'deliverTo:id,organization_id,name',
        ]);

        return Inertia::render('inventory/PurchaseRequestShow', [
            'purchaseRequest' => (new PurchaseRequestResource($purchaseRequest))->resolve($request),
            'approvals' => ApprovalResource::collection(
                Approval::query()
                    ->where('subject_type', $purchaseRequest->getMorphClass())
                    ->where('subject_id', $purchaseRequest->id)
                    ->with(['approver:id,name,avatar_path', 'decider:id,name,avatar_path'])
                    ->oldest()
                    ->get(),
            ),
            'runs' => WorkflowRunResource::collection(
                WorkflowRun::query()
                    ->where('subject_type', $purchaseRequest->getMorphClass())
                    ->where('subject_id', $purchaseRequest->id)
                    ->with('workflow:id,organization_id,name')
                    ->latest()
                    ->get(),
            ),
            'receipts' => InventoryMovementResource::collection(
                $purchaseRequest->movements()
                    ->with(['item:id,organization_id,sku,name,unit', 'toLocation:id,organization_id,name', 'performer:id,name,avatar_path'])
                    ->latest('occurred_at')
                    ->get(),
            ),
            'history' => Inertia::defer(fn () => ActivityLog::query()
                ->where('subject_type', $purchaseRequest->getMorphClass())
                ->where('subject_id', $purchaseRequest->id)
                ->with('actor:id,name,avatar_path')
                ->oldest('created_at')
                ->get()
                ->map(fn (ActivityLog $log): array => $presenter->present($log))),
            'locations' => fn () => InventoryLocation::query()->where('is_active', true)->orderBy('name')->get(['id', 'organization_id', 'name'])
                ->map(fn (InventoryLocation $location): array => ['id' => $location->id, 'name' => $location->name]),
            'hasApprovalWorkflow' => $submit->hasApprovalWorkflow(),
            'can' => [
                'decide' => $user->can('decide', $purchaseRequest),
                'order' => $user->can('order', $purchaseRequest),
                'receive' => $user->can('receive', $purchaseRequest),
                'cancel' => $user->can('cancel', $purchaseRequest),
            ],
        ]);
    }

    /**
     * Approve or reject by hand (for organizations without an approval
     * workflow), mark as ordered, or cancel.
     */
    public function updateStatus(PurchaseRequestStatusRequest $request, PurchaseRequest $purchaseRequest, ChangePurchaseRequestStatus $change): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $status = $request->status();

        $change->handle($purchaseRequest, $user, $status, $request->supplierReference());

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$purchaseRequest->reference()} is now {$status->label()}."]);

        return back();
    }

    public function receive(ReceivePurchaseRequestRequest $request, PurchaseRequest $purchaseRequest, ReceivePurchaseRequest $receive): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $quantity = (int) $request->validated('quantity');
        $location = $request->validated('location_id');

        $received = $receive->handle($purchaseRequest, $user, $quantity, is_string($location) ? $location : null, 'receipt:'.$request->validated('request_key'));

        Inertia::flash('toast', ['type' => 'success', 'message' => $received->status === PurchaseRequestStatus::Received
            ? "{$received->reference()} received in full."
            : "Received {$quantity} against {$received->reference()}."]);

        return back();
    }
}
