<?php

namespace App\Actions\Billing;

use App\Enums\Plan;
use App\Enums\SubscriptionStatus;
use App\Models\Organization;
use App\Models\PlanChangeRequest;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\PlanNoticeNotification;
use App\Support\Activity\ActivityLogger;
use App\Support\Billing\BillingContacts;
use App\Support\Billing\Entitlements;
use App\Support\Tenancy\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Puts an organization on a plan. Used when an owner moves to Free, when a
 * trial ends, and by the FlowPilot team settling an upgrade request (and, once
 * connected, by a payment provider's webhooks).
 */
class ChangePlan
{
    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly Entitlements $entitlements,
        private readonly BillingContacts $contacts,
        private readonly Tenancy $tenancy,
    ) {}

    /**
     * @param  array<string, int|null>|null  $limitOverrides  Custom limits, for Enterprise.
     */
    public function handle(
        Organization $organization,
        Plan $plan,
        ?User $actor,
        string $reason,
        ?array $limitOverrides = null,
        bool $notify = true,
    ): Subscription {
        return $this->tenancy->run($organization, function () use ($organization, $plan, $actor, $reason, $limitOverrides, $notify): Subscription {
            $subscription = DB::transaction(function () use ($plan, $actor, $reason, $limitOverrides): Subscription {
                $subscription = Subscription::query()->lockForUpdate()->firstOrNew([]);
                $from = $subscription->exists ? $subscription->effectivePlan() : null;

                $subscription->forceFill([
                    'plan' => $plan,
                    'status' => SubscriptionStatus::Active,
                    'trial_ends_at' => null,
                    'canceled_at' => null,
                    'current_period_start' => now(),
                    'current_period_end' => $plan === Plan::Free ? null : now()->addMonthNoOverflow(),
                    'limit_overrides' => $plan === Plan::Enterprise ? $limitOverrides : null,
                ])->save();

                // An upgrade request for this plan, or any lower one, is settled.
                PlanChangeRequest::query()
                    ->where('status', PlanChangeRequest::PENDING)
                    ->get()
                    ->each(fn (PlanChangeRequest $request) => $request->forceFill([
                        'status' => $request->to_plan === $plan ? PlanChangeRequest::APPROVED : PlanChangeRequest::WITHDRAWN,
                        'decided_by' => $actor?->id,
                        'decided_at' => now(),
                    ])->save());

                $this->activity->log('billing.plan_changed', $subscription, [
                    'from' => $from?->value,
                    'to' => $plan->value,
                    'reason' => $reason,
                ], actor: $actor, actorType: $actor !== null ? 'user' : 'system');

                return $subscription;
            });

            $this->entitlements->forget($organization);

            if ($notify) {
                Notification::send($this->contacts->for($organization), new PlanNoticeNotification(
                    "{$organization->name} is now on the {$plan->label()} plan",
                    $reason,
                    $plan === Plan::Free ? 'warning' : 'success',
                ));
            }

            return $subscription;
        });
    }
}
