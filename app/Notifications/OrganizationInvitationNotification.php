<?php

namespace App\Notifications;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrganizationInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  string  $token  The plain invitation token. It only ever exists in this email link.
     */
    public function __construct(
        public Invitation $invitation,
        public string $token,
        public string $inviterName,
    ) {
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
        $organization = $this->invitation->organization;

        return (new MailMessage)
            ->subject("{$this->inviterName} invited you to {$organization->name} on FlowPilot")
            ->greeting("Join {$organization->name}")
            ->line("{$this->inviterName} invited you to join {$organization->name} on FlowPilot as {$this->invitation->role->label()}.")
            ->action('Accept invitation', route('invitations.show', ['token' => $this->token]))
            ->line("This link works until {$this->invitation->expires_at->toFormattedDayDateString()}.")
            ->line('If you were not expecting this invitation, you can ignore this email.');
    }
}
