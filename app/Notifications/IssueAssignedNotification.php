<?php

namespace App\Notifications;

use App\Enums\IssueSeverity;
use App\Enums\NotificationType;
use App\Models\Issue;

class IssueAssignedNotification extends TenantNotification
{
    public string $issueId;

    public string $reference;

    public string $issueTitle;

    public string $severity;

    public string $severityValue;

    public function __construct(Issue $issue, public ?string $assignedBy = null)
    {
        parent::__construct();

        $this->issueId = $issue->id;
        $this->reference = $issue->reference();
        $this->issueTitle = $issue->title;
        $this->severity = $issue->severity->label();
        $this->severityValue = $issue->severity->value;
    }

    public function type(): NotificationType
    {
        return NotificationType::IssueAssigned;
    }

    public function title(): string
    {
        return "{$this->reference} ({$this->severity}): {$this->issueTitle}";
    }

    public function body(): ?string
    {
        return ($this->assignedBy ?? 'Someone').' assigned this issue to you.';
    }

    public function url(): ?string
    {
        return $this->tenantRoute('issues.show', ['issue' => $this->issueId]);
    }

    public function tone(): string
    {
        return IssueSeverity::from($this->severityValue)->weight() >= IssueSeverity::High->weight() ? 'danger' : 'warning';
    }

    public function actorName(): ?string
    {
        return $this->assignedBy;
    }

    public function actionText(): string
    {
        return 'Open issue';
    }
}
