<?php

namespace Database\Factories;

use App\Enums\CompanySize;
use App\Enums\Industry;
use App\Enums\OrganizationStatus;
use App\Enums\Plan;
use App\Enums\Role;
use App\Enums\SubscriptionStatus;
use App\Enums\UseCase;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'owner_id' => User::factory(),
            'status' => OrganizationStatus::Active,
            'industry' => fake()->randomElement(Industry::cases()),
            'company_size' => fake()->randomElement(CompanySize::cases()),
            'primary_use_case' => fake()->randomElement(UseCase::cases()),
            'timezone' => 'UTC',
            'currency' => 'USD',
            'onboarded_at' => now(),
        ];
    }

    /**
     * Give the owner an Owner membership, as CreateOrganization does.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Organization $organization): void {
            OrganizationMembership::query()->firstOrCreate(
                ['organization_id' => $organization->id, 'user_id' => $organization->owner_id],
                ['role' => Role::Owner, 'joined_at' => now()],
            );

            if (! $organization->subscription()->exists()) {
                $organization->subscription()->create(['plan' => Plan::Business, 'status' => SubscriptionStatus::Active]);
            }
        });
    }

    /**
     * On a given plan, active (or on a trial when one is given).
     */
    public function onPlan(Plan $plan, ?SubscriptionStatus $status = null): static
    {
        return $this->afterCreating(function (Organization $organization) use ($plan, $status): void {
            $organization->subscription()->updateOrCreate([], [
                'plan' => $plan,
                'status' => $status ?? SubscriptionStatus::Active,
                'trial_ends_at' => $status === SubscriptionStatus::Trialing ? now()->addDays(7) : null,
            ]);
        });
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => ['status' => OrganizationStatus::Suspended]);
    }
}
