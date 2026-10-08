<?php

namespace App\Notifications\Channels;

use App\Notifications\TenantNotification;
use App\Support\Tenancy\Tenancy;
use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Notifications\Notification;

/**
 * Stores database notifications with the organization they belong to, so the
 * notification center can show one workspace's notifications at a time.
 */
class TenantDatabaseChannel extends DatabaseChannel
{
    /**
     * @return array<string, mixed>
     */
    protected function buildPayload($notifiable, Notification $notification)
    {
        return [
            ...parent::buildPayload($notifiable, $notification),
            'organization_id' => $notification instanceof TenantNotification
                ? $notification->organizationId
                : app(Tenancy::class)->id(),
        ];
    }
}
