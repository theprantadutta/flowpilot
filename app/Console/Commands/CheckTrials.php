<?php

namespace App\Console\Commands;

use App\Actions\Billing\ChangePlan;
use App\Enums\Plan;
use App\Enums\SubscriptionStatus;
use App\Models\Organization;
use App\Models\Subscription;
use App\Notifications\PlanNoticeNotification;
use App\Support\Billing\BillingContacts;
use App\Support\Tenancy\Tenancy;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Reminds owners three days before a trial ends, and moves organizations
 * whose trial has ended to the Free plan. Crosses organizations to find
 * them, then handles each inside its own organization.
 */
#[Signature('billing:check-trials')]
#[Description('Remind owners about ending trials and move ended trials to Free')]
class CheckTrials extends Command
{
    public function handle(Tenancy $tenancy, ChangePlan $changePlan, BillingContacts $contacts): int
    {
        $reminded = 0;
        $ended = 0;

        Subscription::withoutOrganizationScope()
            ->where('status', SubscriptionStatus::Trialing->value)
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<=', now()->addDays(3))
            ->orderBy('trial_ends_at')
            ->chunkById(100, function ($subscriptions) use ($tenancy, $changePlan, $contacts, &$reminded, &$ended): void {
                foreach ($subscriptions as $subscription) {
                    $organization = Organization::query()->find($subscription->organization_id);

                    if ($organization === null || ! $organization->isActive()) {
                        continue;
                    }

                    if ($subscription->trial_ends_at?->isPast()) {
                        $changePlan->handle($organization, Plan::Free, null, "Your {$subscription->plan->label()} trial has ended, so {$organization->name} moved to the Free plan. Nothing was deleted; upgrade any time to get everything back.");
                        $ended++;

                        continue;
                    }

                    if ($subscription->trial_reminded_at !== null) {
                        continue;
                    }

                    $tenancy->run($organization, function () use ($organization, $subscription, $contacts): void {
                        $days = $subscription->trialDaysLeft() ?? 0;

                        Notification::send($contacts->for($organization), new PlanNoticeNotification(
                            "Your {$subscription->plan->label()} trial ends in {$days} ".($days === 1 ? 'day' : 'days'),
                            'Choose a plan to keep everything you are using. Otherwise the organization moves to Free when the trial ends; nothing is deleted.',
                            'warning',
                        ));
                    });

                    $subscription->forceFill(['trial_reminded_at' => now()])->save();
                    $reminded++;
                }
            });

        $this->components->info("Reminded {$reminded} and ended {$ended} trials.");

        return self::SUCCESS;
    }
}
