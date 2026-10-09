<?php

namespace App\Models;

use App\Enums\Plan;
use App\Enums\SubscriptionStatus;
use App\Models\Concerns\BelongsToOrganization;
use Carbon\CarbonImmutable;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The organization's plan and where it stands: trial, active, overdue or
 * canceled. Ready for a payment provider (provider and its subscription id)
 * without depending on one.
 *
 * @property string $id
 * @property string $organization_id
 * @property Plan $plan
 * @property SubscriptionStatus $status
 * @property CarbonImmutable|null $trial_ends_at
 * @property CarbonImmutable|null $trial_reminded_at
 * @property CarbonImmutable|null $current_period_start
 * @property CarbonImmutable|null $current_period_end
 * @property CarbonImmutable|null $canceled_at
 * @property array<string, int|null>|null $limit_overrides
 * @property string|null $provider
 * @property string|null $provider_subscription_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['plan', 'status', 'trial_ends_at', 'trial_reminded_at', 'current_period_start', 'current_period_end', 'canceled_at', 'limit_overrides', 'provider', 'provider_subscription_id'])]
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use BelongsToOrganization, HasFactory, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'trial_ends_at' => null,
        'trial_reminded_at' => null,
        'current_period_start' => null,
        'current_period_end' => null,
        'canceled_at' => null,
        'limit_overrides' => null,
        'provider' => null,
        'provider_subscription_id' => null,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'plan' => Plan::class,
            'status' => SubscriptionStatus::class,
            'trial_ends_at' => 'datetime',
            'trial_reminded_at' => 'datetime',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'canceled_at' => 'datetime',
            'limit_overrides' => 'array',
        ];
    }

    public function isOnTrial(): bool
    {
        return $this->status === SubscriptionStatus::Trialing
            && $this->trial_ends_at !== null
            && $this->trial_ends_at->isFuture();
    }

    /**
     * The plan whose features and limits apply right now. A trial that has
     * run out, or a canceled subscription, falls back to Free.
     */
    public function effectivePlan(): Plan
    {
        return match ($this->status) {
            SubscriptionStatus::Trialing => $this->isOnTrial() ? $this->plan : Plan::Free,
            SubscriptionStatus::Canceled => Plan::Free,
            // Overdue keeps the plan while payment is sorted out.
            SubscriptionStatus::Active, SubscriptionStatus::PastDue => $this->plan,
        };
    }

    public function trialDaysLeft(): ?int
    {
        return $this->isOnTrial() && $this->trial_ends_at !== null
            ? max(0, (int) ceil(now()->diffInHours($this->trial_ends_at) / 24))
            : null;
    }
}
