<?php

namespace App\Actions\Inventory;

use App\Enums\PurchaseRequestStatus;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Models\WorkflowRun;
use App\Notifications\PurchaseRequestUpdatedNotification;
use App\Support\Activity\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Moves a purchase request along: approved or rejected, ordered, cancelled.
 * Receiving has its own action because it changes stock.
 */
class ChangePurchaseRequestStatus
{
    /**
     * Where each status may go next.
     */
    public const array TRANSITIONS = [
        'submitted' => ['approved', 'rejected', 'cancelled'],
        'approved' => ['ordered', 'cancelled'],
        'ordered' => ['cancelled'],
        'rejected' => [],
        'received' => [],
        'cancelled' => [],
    ];

    public function __construct(private readonly ActivityLogger $activity) {}

    /**
     * @throws ValidationException When the request cannot move to that status from where it is.
     */
    public function handle(PurchaseRequest $request, ?User $actor, PurchaseRequestStatus $to, ?string $supplierReference = null, ?WorkflowRun $automation = null): PurchaseRequest
    {
        $request = DB::transaction(function () use ($request, $actor, $to, $supplierReference, $automation): PurchaseRequest {
            /** @var PurchaseRequest $locked */
            $locked = PurchaseRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if (! in_array($to->value, self::TRANSITIONS[$locked->status->value], true)) {
                throw ValidationException::withMessages([
                    'status' => "{$locked->reference()} is {$locked->status->label()} and cannot be marked {$to->label()}.",
                ]);
            }

            $locked->forceFill(match ($to) {
                PurchaseRequestStatus::Approved, PurchaseRequestStatus::Rejected => ['status' => $to, 'decided_by' => $actor?->id, 'decided_at' => now()],
                PurchaseRequestStatus::Ordered => ['status' => $to, 'ordered_at' => now(), 'supplier_reference' => $supplierReference ?? $locked->supplier_reference],
                PurchaseRequestStatus::Cancelled => ['status' => $to, 'cancelled_at' => now()],
                default => ['status' => $to],
            })->save();

            $this->activity->log('purchase_request.'.$to->value, $locked, [
                'reference' => $locked->reference(),
                'title' => $locked->summary(),
                ...ActivityLogger::automation($automation),
            ], actor: $actor, actorType: $automation && ! $actor ? 'workflow' : 'user');

            return $locked;
        });

        if ($request->requester_id !== null && $request->requester_id !== $actor?->id) {
            $request->requester?->notify(new PurchaseRequestUpdatedNotification(
                $request,
                $automation ? ActivityLogger::automation($automation)['workflow'] : $actor?->name,
            ));
        }

        return $request;
    }
}
