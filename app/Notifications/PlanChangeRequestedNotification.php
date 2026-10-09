<?php

namespace App\Notifications;

use App\Models\Organization;
use App\Models\PlanChangeRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the FlowPilot team an organization wants a higher plan. Sent to
 * platform administrators by email; it is not tenant work.
 */
class PlanChangeRequestedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public string $organizationName;

    public string $organizationId;

    public string $from;

    public string $to;

    public string $requesterName;

    public string $requesterEmail;

    public ?string $message;

    public function __construct(Organization $organization, PlanChangeRequest $request, User $requester)
    {
        $this->organizationId = $organization->id;
        $this->organizationName = $organization->name;
        $this->from = $request->from_plan->label();
        $this->to = $request->to_plan->label();
        $this->requesterName = $requester->name;
        $this->requesterEmail = $requester->email;
        $this->message = $request->message;
        $this->afterCommit();
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
        $mail = (new MailMessage)
            ->subject("{$this->organizationName} wants the {$this->to} plan")
            ->greeting('An upgrade request came in')
            ->line("{$this->requesterName} ({$this->requesterEmail}) asked to move {$this->organizationName} from {$this->from} to {$this->to}.");

        if ($this->message !== null && $this->message !== '') {
            $mail->line('Their note: '.$this->message);
        }

        return $mail->line('Change the plan from platform administration once it is agreed.');
    }
}
