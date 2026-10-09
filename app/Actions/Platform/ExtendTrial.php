<?php

namespace App\Actions\Platform;

use App\Enums\Plan;
use App\Enums\SubscriptionStatus;
use App\Models\Organization;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\PlanNoticeNotification;
use App\Support\Activity\ActivityLogger;
use App\Support\Billing\BillingContacts;
use App\Support\Billing\Entitlements;
use App\Support\Tenancy\Tenancy;
use Illuminate\Support\Facades\Notification;

/**
 * Gives an organization more time on a trial, for example while an upgrade
 * is being agreed. Starts a trial of the given plan if none is running.
 */
class ExtendTrial
{
    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly Entitlements $entitlements,
        private readonly BillingContacts $contacts,
        private readonly Tenancy $tenancy,
    ) {}

    public function handle(Organization $organization, User $admin, int $days, Plan $plan): Subscription
    {
        return $this->tenancy->run($organization, function () use ($organization, $admin, $days, $plan): Subscription {
            $subscription = Subscription::query()->firstOrNew([]);
            $from = $subscription->isOnTrial() && $subscription->trial_ends_at !== null ? $subscription->trial_ends_at : now();

            $subscription->forceFill([
                'plan' => $plan,
                'status' => SubscriptionStatus::Trialing,
                'trial_ends_at' => $from->addDays($days),
                'trial_reminded_at' => null,
                'canceled_at' => null,
            ])->save();

            $this->entitlements->forget($organization);

            $this->activity->log('platform.trial_extended', $subscription, [
                'plan' => $plan->value,
                'days' => $days,
                'until' => $subscription->trial_ends_at?->toDateString(),
            ], actor: $admin, actorType: 'platform', organization: $organization);

            Notification::send($this->contacts->for($organization), new PlanNoticeNotification(
                "Your {$plan->label()} trial now runs until ".$subscription->trial_ends_at?->format('M j, Y'),
                'The FlowPilot team extended it. Everything in the plan stays available until then.',
                'success',
            ));

            return $subscription;
        });
    }
}
