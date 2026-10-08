<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Invitation;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Invitation>
 */
class InvitationFactory extends Factory
{
    /**
     * The plain token of the most recently made invitation, for tests that need the link.
     */
    public static ?string $lastToken = null;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        static::$lastToken = Str::random(48);

        return [
            'organization_id' => Organization::factory(),
            'email' => fake()->unique()->safeEmail(),
            'role' => Role::Employee,
            'token_hash' => Invitation::hashToken(static::$lastToken),
            'expires_at' => now()->addDays(7),
        ];
    }

    /**
     * Use a known token so a test can open the invitation link.
     */
    public function withToken(string $token): static
    {
        return $this->state(fn (): array => ['token_hash' => Invitation::hashToken($token)]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => ['expires_at' => now()->subDay()]);
    }

    public function revoked(): static
    {
        return $this->state(fn (): array => ['revoked_at' => now()]);
    }
}
