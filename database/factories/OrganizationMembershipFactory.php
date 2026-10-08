<?php

namespace Database\Factories;

use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationMembership>
 */
class OrganizationMembershipFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
            'role' => Role::Employee,
            'status' => MembershipStatus::Active,
            'joined_at' => now(),
        ];
    }

    public function role(Role $role): static
    {
        return $this->state(fn (): array => ['role' => $role]);
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => ['status' => MembershipStatus::Suspended]);
    }
}
