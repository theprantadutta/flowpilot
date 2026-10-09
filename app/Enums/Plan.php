<?php

namespace App\Enums;

/**
 * The plans an organization can be on. What each includes lives in
 * config/billing.php and is read through App\Support\Billing\Entitlements.
 */
enum Plan: string
{
    case Free = 'free';
    case Starter = 'starter';
    case Business = 'business';
    case Enterprise = 'enterprise';

    public function label(): string
    {
        return (string) config("billing.plans.{$this->value}.label", ucfirst($this->value));
    }

    public function tagline(): string
    {
        return (string) config("billing.plans.{$this->value}.tagline", '');
    }

    /**
     * Price per month in minor units, or null when it is agreed per customer.
     */
    public function monthlyPrice(): ?int
    {
        $price = config("billing.plans.{$this->value}.monthly_price");

        return is_int($price) ? $price : null;
    }

    /**
     * Higher plans include everything lower ones do.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Free => 0,
            self::Starter => 1,
            self::Business => 2,
            self::Enterprise => 3,
        };
    }

    public function includes(Feature $feature): bool
    {
        return in_array($feature->value, (array) config("billing.plans.{$this->value}.features", []), true);
    }

    public function limit(Limit $limit): ?int
    {
        $value = config("billing.plans.{$this->value}.limits.{$limit->value}");

        return is_int($value) ? $value : null;
    }

    /**
     * The lowest plan that includes a feature, to say what to upgrade to.
     */
    public static function lowestWith(Feature $feature): ?self
    {
        foreach (self::cases() as $plan) {
            if ($plan->includes($feature)) {
                return $plan;
            }
        }

        return null;
    }
}
