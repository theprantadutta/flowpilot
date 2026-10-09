<?php

namespace App\Support\Billing;

use App\Enums\Feature;
use App\Enums\Limit;
use App\Enums\Plan;
use App\Models\Organization;
use App\Support\Money;
use Illuminate\Support\Number;

/**
 * Everything the plan pages show about an organization: its subscription,
 * what it uses against each limit, and the plans it could be on.
 */
class BillingSummary
{
    public function __construct(private readonly Entitlements $entitlements) {}

    /**
     * @return array<string, mixed>
     */
    public function for(Organization $organization): array
    {
        $subscription = $this->entitlements->subscription($organization);
        $plan = $this->entitlements->plan($organization);
        $usages = $this->entitlements->usages($organization);

        return [
            'plan' => ['value' => $plan->value, 'label' => $plan->label(), 'price' => $this->price($plan)],
            'subscribed_plan' => $subscription !== null ? ['value' => $subscription->plan->value, 'label' => $subscription->plan->label()] : null,
            'status' => $subscription?->status->toOption(),
            'on_trial' => (bool) $subscription?->isOnTrial(),
            'trial_ends_at' => $subscription?->trial_ends_at?->toIso8601String(),
            'trial_days_left' => $subscription?->trialDaysLeft(),
            'current_period_end' => $subscription?->current_period_end?->toIso8601String(),
            'has_custom_limits' => $subscription?->limit_overrides !== null && $subscription->limit_overrides !== [],
            'usage' => array_map(fn (Limit $limit): array => [
                'key' => $limit->value,
                'label' => $limit->label(),
                'used' => $usages[$limit->value],
                'limit' => $this->entitlements->limit($limit, $organization),
                'unit' => $limit === Limit::StorageMb ? 'mb' : 'count',
            ], Limit::cases()),
        ];
    }

    /**
     * The plans side by side, for choosing one.
     *
     * @return list<array{value: string, label: string, tagline: string, price: string|null, monthly_price: int|null, rank: int, limits: list<array{label: string, included: bool}>, features: list<array{value: string, label: string, included: bool}>}>
     */
    public function catalog(): array
    {
        return array_map(fn (Plan $plan): array => [
            'value' => $plan->value,
            'label' => $plan->label(),
            'tagline' => $plan->tagline(),
            'price' => $this->price($plan),
            'monthly_price' => $plan->monthlyPrice(),
            'rank' => $plan->rank(),
            'limits' => array_map(fn (Limit $limit): array => [
                'label' => $limit->describe($plan->limit($limit)),
                'included' => $plan->limit($limit) !== 0,
            ], Limit::cases()),
            'features' => array_map(fn (Feature $feature): array => [
                'value' => $feature->value,
                'label' => $feature->label(),
                'included' => $plan->includes($feature),
            ], Feature::cases()),
        ], Plan::cases());
    }

    /**
     * The plans compared row by row, for the pricing page: each limit with
     * its value per plan, then each feature with whether a plan includes it.
     *
     * @return array{plans: list<string>, limits: list<array{label: string, values: list<string|null>}>, features: list<array{label: string, included: list<bool>}>}
     */
    public function comparison(): array
    {
        return [
            'plans' => array_map(fn (Plan $plan): string => $plan->label(), Plan::cases()),
            'limits' => array_map(fn (Limit $limit): array => [
                'label' => $limit->capLabel(),
                'values' => array_map(fn (Plan $plan): ?string => $limit->shortValue($plan->limit($limit)), Plan::cases()),
            ], Limit::cases()),
            'features' => array_map(fn (Feature $feature): array => [
                'label' => $feature->label(),
                'included' => array_map(fn (Plan $plan): bool => $plan->includes($feature), Plan::cases()),
            ], Feature::cases()),
        ];
    }

    /**
     * "$29" for whole amounts, "$29.50" otherwise.
     */
    private function price(Plan $plan): ?string
    {
        $price = $plan->monthlyPrice();
        $currency = (string) config('billing.currency', 'USD');

        if ($price === null) {
            return null;
        }

        return $price % 100 === 0
            ? (Number::currency(intdiv($price, 100), in: $currency, precision: 0) ?: Money::format($price, $currency))
            : Money::format($price, $currency);
    }
}
