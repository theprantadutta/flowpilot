<?php

namespace App\Notifications;

use App\Enums\ApprovalStatus;
use App\Enums\NotificationType;
use App\Models\Approval;

class ApprovalDecidedNotification extends TenantNotification
{
    public string $approvalId;

    public string $reference;

    public string $approvalTitle;

    public string $status;

    public ?string $note;

    public function __construct(Approval $approval, public ?string $decidedBy)
    {
        parent::__construct();

        $this->approvalId = $approval->id;
        $this->reference = $approval->reference();
        $this->approvalTitle = $approval->title;
        $this->status = $approval->status->value;
        $this->note = $approval->decision_note;
    }

    public function type(): NotificationType
    {
        return NotificationType::ApprovalDecided;
    }

    public function title(): string
    {
        return match (ApprovalStatus::from($this->status)) {
            ApprovalStatus::Approved => "{$this->reference} approved: {$this->approvalTitle}",
            ApprovalStatus::Rejected => "{$this->reference} rejected: {$this->approvalTitle}",
            ApprovalStatus::ChangesRequested => "Changes requested on {$this->reference}: {$this->approvalTitle}",
            ApprovalStatus::Expired => "{$this->reference} expired: {$this->approvalTitle}",
            default => "{$this->reference} updated: {$this->approvalTitle}",
        };
    }

    public function body(): ?string
    {
        $who = $this->decidedBy ?? 'Nobody';
        $sentence = match (ApprovalStatus::from($this->status)) {
            ApprovalStatus::Approved => "{$who} approved your request.",
            ApprovalStatus::Rejected => "{$who} rejected your request.",
            ApprovalStatus::ChangesRequested => "{$who} asked for changes before deciding.",
            ApprovalStatus::Expired => 'Nobody decided before the due date, so the request expired.',
            default => null,
        };

        return $this->note ? trim("{$sentence} “{$this->note}”") : $sentence;
    }

    public function url(): ?string
    {
        return $this->tenantRoute('approvals.show', ['approval' => $this->approvalId]);
    }

    public function tone(): string
    {
        return match (ApprovalStatus::from($this->status)) {
            ApprovalStatus::Approved => 'success',
            ApprovalStatus::Rejected, ApprovalStatus::Expired => 'danger',
            default => 'warning',
        };
    }

    public function actorName(): ?string
    {
        return $this->decidedBy;
    }

    public function actionText(): string
    {
        return 'Open request';
    }
}
