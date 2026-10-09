<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Enums\PurchaseRequestStatus;
use App\Models\PurchaseRequest;

/**
 * Tells the requester how their purchase request is getting on.
 */
class PurchaseRequestUpdatedNotification extends TenantNotification
{
    public string $requestId;

    public string $reference;

    public string $summary;

    public string $status;

    public function __construct(PurchaseRequest $request, public ?string $updatedBy)
    {
        parent::__construct();

        $this->requestId = $request->id;
        $this->reference = $request->reference();
        $this->summary = $request->summary();
        $this->status = $request->status->value;
    }

    public function type(): NotificationType
    {
        return NotificationType::PurchaseRequestUpdated;
    }

    public function title(): string
    {
        return match (PurchaseRequestStatus::from($this->status)) {
            PurchaseRequestStatus::Approved => "{$this->reference} approved: {$this->summary}",
            PurchaseRequestStatus::Rejected => "{$this->reference} not approved: {$this->summary}",
            PurchaseRequestStatus::Ordered => "{$this->reference} ordered: {$this->summary}",
            PurchaseRequestStatus::Received => "{$this->reference} received: {$this->summary}",
            PurchaseRequestStatus::Cancelled => "{$this->reference} cancelled: {$this->summary}",
            PurchaseRequestStatus::Submitted => "{$this->reference} submitted: {$this->summary}",
        };
    }

    public function body(): ?string
    {
        $who = $this->updatedBy ?? 'FlowPilot';

        return match (PurchaseRequestStatus::from($this->status)) {
            PurchaseRequestStatus::Approved => "{$who} approved your purchase request.",
            PurchaseRequestStatus::Rejected => "{$who} did not approve your purchase request.",
            PurchaseRequestStatus::Ordered => "{$who} placed the order.",
            PurchaseRequestStatus::Received => "{$who} received it into stock.",
            PurchaseRequestStatus::Cancelled => "{$who} cancelled the request.",
            PurchaseRequestStatus::Submitted => "{$who} raised it. No approval workflow is switched on, so it needs a decision from someone who manages inventory.",
        };
    }

    public function url(): ?string
    {
        return $this->tenantRoute('purchase-requests.show', ['purchaseRequest' => $this->requestId]);
    }

    public function tone(): string
    {
        return PurchaseRequestStatus::from($this->status)->tone();
    }

    public function actorName(): ?string
    {
        return $this->updatedBy;
    }

    public function actionText(): string
    {
        return 'Open request';
    }
}
