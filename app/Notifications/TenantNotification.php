<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\Organization;
use App\Models\User;
use App\Support\Notifications\NotificationPreferences;
use App\Support\Tenancy\Tenancy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Base for notifications about work inside an organization.
 *
 * Every one lands in the member's notification center for that organization
 * and is emailed when their preferences say so. Subclasses describe the
 * message once; the in-app and email versions are built from it.
 */
abstract class TenantNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public string $organizationId;

    public string $organizationSlug;

    public function __construct()
    {
        $organization = app(Tenancy::class)->currentOrFail();

        $this->organizationId = $organization->id;
        $this->organizationSlug = $organization->slug;
        $this->afterCommit();
    }

    /**
     * A URL inside the organization. Built explicitly because notifications are
     * rendered by a queue worker, outside the request that set the URL defaults.
     *
     * @param  array<string, mixed>  $parameters
     */
    protected function tenantRoute(string $name, array $parameters = []): string
    {
        return route($name, ['organization' => $this->organizationSlug, ...$parameters]);
    }

    abstract public function type(): NotificationType;

    /**
     * One-line summary, e.g. "Approve purchase request #1842".
     */
    abstract public function title(): string;

    /**
     * Supporting sentence shown under the title.
     */
    abstract public function body(): ?string;

    /**
     * Where opening the notification takes the member.
     */
    abstract public function url(): ?string;

    /**
     * Colour role in the notification center: info, warning, danger, success, flow or ai.
     */
    public function tone(): string
    {
        return 'info';
    }

    /**
     * The person whose action caused the notification, if any.
     */
    public function actorName(): ?string
    {
        return null;
    }

    /**
     * Label for the email button.
     */
    public function actionText(): string
    {
        return 'Open in FlowPilot';
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($notifiable instanceof User && app(NotificationPreferences::class)->wantsEmail($notifiable, $this->type(), $this->organization())) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * @return array{type: string, title: string, body: string|null, url: string|null, tone: string, actor: string|null}
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => $this->type()->value,
            'title' => $this->title(),
            'body' => $this->body(),
            'url' => $this->url(),
            'tone' => $this->tone(),
            'actor' => $this->actorName(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $organization = $this->organization();

        $message = (new MailMessage)
            ->subject($this->title())
            ->greeting($this->title());

        if ($this->body()) {
            $message->line($this->body());
        }

        if ($this->url()) {
            $message->action($this->actionText(), $this->url());
        }

        return $message->line("You are receiving this because you are a member of {$organization?->name}. You can change which emails you get in your notification settings.");
    }

    public function databaseType(object $notifiable): string
    {
        return $this->type()->value;
    }

    protected function organization(): ?Organization
    {
        return Organization::query()->find($this->organizationId);
    }
}
