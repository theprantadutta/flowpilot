<?php

namespace App\Actions\Inventory;

use App\Enums\PurchaseRequestStatus;
use App\Enums\WorkflowStatus;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowRun;
use App\Notifications\PurchaseRequestUpdatedNotification;
use App\Support\Activity\ActivityLogger;
use App\Support\Inventory\InventoryManagers;
use App\Support\Tenancy\OrganizationSequence;
use App\Support\Tenancy\Tenancy;
use App\Workflows\Engine\WorkflowTriggers;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Raise a purchase request. Approval is the job of whichever workflow listens
 * for new purchase requests; with none switched on, inventory managers are
 * told it needs a decision.
 */
class SubmitPurchaseRequest
{
    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly OrganizationSequence $sequence,
        private readonly WorkflowTriggers $triggers,
        private readonly InventoryManagers $managers,
        private readonly Tenancy $tenancy,
    ) {}

    /**
     * @param  array{item_name: string, inventory_item_id?: string|null, supplier_id?: string|null, deliver_to_location_id?: string|null, quantity: int, unit_cost_amount: int, needed_by?: string|null, reason?: string|null, idempotency_key?: string|null}  $attributes
     */
    public function handle(?User $requester, array $attributes, ?WorkflowRun $automation = null): PurchaseRequest
    {
        $key = $attributes['idempotency_key'] ?? null;

        if ($key !== null && ($existing = PurchaseRequest::query()->where('idempotency_key', $key)->first())) {
            return $existing;
        }

        $organization = $this->tenancy->currentOrFail();

        try {
            $request = DB::transaction(function () use ($requester, $attributes, $automation, $organization): PurchaseRequest {
                $request = PurchaseRequest::query()->create([
                    ...$attributes,
                    'number' => $this->sequence->next('purchase_requests'),
                    'status' => PurchaseRequestStatus::Submitted,
                    'requester_id' => $requester?->id,
                    'total_amount' => $attributes['quantity'] * $attributes['unit_cost_amount'],
                    'currency' => $organization->currency,
                ]);

                $this->activity->log('purchase_request.submitted', $request, [
                    'reference' => $request->reference(),
                    'title' => $request->summary(),
                    ...ActivityLogger::automation($automation),
                ], actor: $requester, actorType: $automation && ! $requester ? 'workflow' : 'user');

                $this->triggers->fire('purchase_request.submitted', $request, $requester, $automation);

                return $request;
            });
        } catch (UniqueConstraintViolationException $exception) {
            if ($key !== null && ($existing = PurchaseRequest::query()->where('idempotency_key', $key)->first())) {
                return $existing;
            }

            throw $exception;
        }

        if (! $this->hasApprovalWorkflow()) {
            $recipients = $this->managers->for($organization)->reject(fn (User $user): bool => $user->id === $requester?->id);

            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new PurchaseRequestUpdatedNotification($request, $requester?->name));
            }
        }

        return $request;
    }

    public function hasApprovalWorkflow(): bool
    {
        return Workflow::query()
            ->where('trigger_type', 'purchase_request.submitted')
            ->where('status', WorkflowStatus::Active->value)
            ->whereNotNull('current_version_id')
            ->exists();
    }
}
