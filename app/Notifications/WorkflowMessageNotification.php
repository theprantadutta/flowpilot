<?php

namespace App\Notifications;

use App\Enums\NotificationType;

/**
 * A message a workflow step sends to people, written by the workflow's author.
 */
class WorkflowMessageNotification extends TenantNotification
{
    public function __construct(
        public string $messageTitle,
        public ?string $message,
        public ?string $link,
        public string $workflowName,
    ) {
        parent::__construct();
    }

    public function type(): NotificationType
    {
        return NotificationType::WorkflowMessage;
    }

    public function title(): string
    {
        return $this->messageTitle;
    }

    public function body(): ?string
    {
        return $this->message;
    }

    public function url(): ?string
    {
        return $this->link;
    }

    public function tone(): string
    {
        return 'flow';
    }

    public function actorName(): ?string
    {
        return $this->workflowName;
    }
}
