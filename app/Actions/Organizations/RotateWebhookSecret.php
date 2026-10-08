<?php

namespace App\Actions\Organizations;

use App\Models\Organization;
use App\Models\User;
use App\Support\Activity\ActivityLogger;
use Illuminate\Support\Str;

class RotateWebhookSecret
{
    public function __construct(private readonly ActivityLogger $activity) {}

    /**
     * Replace the signing secret. Receivers must switch to the new one; every
     * delivery from now on is signed with it.
     */
    public function handle(Organization $organization, User $actor): string
    {
        $secret = 'whsec_'.Str::random(40);

        $organization->forceFill(['webhook_secret' => $secret])->save();

        $this->activity->log('settings.webhook_secret_rotated', $organization, actor: $actor, organization: $organization);

        return $secret;
    }
}
