<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\Approval;
use App\Support\Money;

class ApprovalRequiredNotification extends TenantNotification
{
    public string $approvalId;

    public string $reference;

    public string $approvalTitle;

    public ?string $requesterName;

    public ?string $amount;

    public ?string $dueAt;

    public function __construct(Approval $approval, public bool $reminder = false)
    {
        parent::__construct();

        $this->approvalId = $approval->id;
        $this->reference = $approval->reference();
        $this->approvalTitle = $approval->title;
        $this->requesterName = $approval->requester?->name;
        $this->amount = $approval->amount !== null && $approval->currency !== null
            ? Money::format($approval->amount, $approval->currency)
            : null;
        $this->dueAt = $approval->due_at?->toDayDateTimeString();
    }

    public function type(): NotificationType
    {
        return NotificationType::ApprovalRequired;
    }

    public function title(): string
    {
        return $this->reminder
            ? "Overdue: {$this->reference} {$this->approvalTitle}"
            : "Approve {$this->reference}: {$this->approvalTitle}";
    }

    public function body(): ?string
    {
        $parts = [($this->requesterName ?? 'A workflow').' is waiting for your decision'];

        if ($this->amount !== null) {
            $parts[] = "on {$this->amount}";
        }

        $sentence = implode(' ', $parts).'.';

        if ($this->dueAt !== null) {
            $sentence .= $this->reminder ? " It was due {$this->dueAt}." : " Due {$this->dueAt}.";
        }

        return $sentence;
    }

    public function url(): ?string
    {
        return $this->tenantRoute('approvals.show', ['approval' => $this->approvalId]);
    }

    public function tone(): string
    {
        return 'warning';
    }

    public function actorName(): ?string
    {
        return $this->requesterName;
    }

    public function actionText(): string
    {
        return 'Review request';
    }
}
