<?php

namespace App\Actions\Organizations;

use App\Enums\Plan;
use App\Enums\Role;
use App\Enums\SubscriptionStatus;
use App\Models\Organization;
use App\Models\User;
use App\Support\Activity\ActivityLogger;
use App\Support\Tenancy\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateOrganization
{
    public function __construct(
        private readonly Tenancy $tenancy,
        private readonly ActivityLogger $activity,
    ) {}

    /**
     * Create an organization and make the given user its owner.
     *
     * @param  array{name: string, industry?: string|null, company_size?: string|null, primary_use_case?: string|null, timezone?: string|null, currency?: string|null, website?: string|null}  $attributes
     */
    public function handle(User $owner, array $attributes): Organization
    {
        return DB::transaction(function () use ($owner, $attributes): Organization {
            $organization = Organization::query()->create([
                'name' => $attributes['name'],
                'slug' => $this->uniqueSlug($attributes['name']),
                'owner_id' => $owner->id,
                'industry' => $attributes['industry'] ?? null,
                'company_size' => $attributes['company_size'] ?? null,
                'primary_use_case' => $attributes['primary_use_case'] ?? null,
                'timezone' => $attributes['timezone'] ?? $owner->timezone ?? 'UTC',
                'currency' => strtoupper($attributes['currency'] ?? 'USD'),
                'website' => $attributes['website'] ?? null,
                'contact_email' => $owner->email,
                'onboarded_at' => now(),
            ]);

            $membership = $organization->memberships()->create([
                'user_id' => $owner->id,
                'role' => Role::Owner,
                'joined_at' => now(),
                'last_active_at' => now(),
            ]);

            // Every organization starts by trying the trial plan.
            $organization->subscription()->create([
                'plan' => Plan::from((string) config('billing.trial_plan', 'business')),
                'status' => SubscriptionStatus::Trialing,
                'trial_ends_at' => now()->addDays((int) config('billing.trial_days', 14)),
            ]);

            $owner->forceFill(['last_organization_id' => $organization->id])->save();
            $owner->unsetRelation('memberships');

            $this->tenancy->run($organization, fn () => $this->activity->log(
                'organization.created',
                $organization,
                actor: $owner,
            ), $membership);

            return $organization;
        });
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::limit(Str::slug($name) ?: 'organization', 48, '');
        $slug = $base;
        $suffix = 2;

        while (Organization::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
