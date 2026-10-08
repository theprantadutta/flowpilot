<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * An email a workflow step sends. Unlike notifications it is always emailed,
 * because sending an email is what the step is for.
 */
class WorkflowEmailNotification extends TenantNotification
{
    public function __construct(
        public string $subject,
        public string $message,
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
        return $this->subject;
    }

    public function body(): ?string
    {
        return $this->message;
    }

    public function url(): ?string
    {
        return $this->link;
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)->subject($this->subject);

        foreach (preg_split('/\R{2,}/', $this->message) ?: [] as $paragraph) {
            if (trim($paragraph) !== '') {
                $message->line(trim($paragraph));
            }
        }

        if ($this->link) {
            $message->action('Open in FlowPilot', $this->link);
        }

        return $message->salutation("Sent by the {$this->workflowName} workflow in {$this->organization()?->name}.");
    }
}
