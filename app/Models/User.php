<?php

namespace App\Models;

use App\Enums\MembershipStatus;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $avatar_path
 * @property string|null $timezone
 * @property array{email?: array<string, bool>}|null $notification_preferences
 * @property bool $is_platform_admin
 * @property string|null $last_organization_id
 * @property CarbonImmutable|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property CarbonImmutable|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read string|null $avatar
 * @property-read Collection<int, OrganizationMembership> $memberships
 */
#[Fillable(['name', 'email', 'password', 'avatar_path', 'timezone', 'notification_preferences'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token', 'avatar_path', 'is_platform_admin'])]
#[Appends(['avatar'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Defaults for columns a freshly created user has not loaded yet.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'avatar_path' => null,
        'timezone' => null,
        'notification_preferences' => null,
        'is_platform_admin' => false,
        'last_organization_id' => null,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'is_platform_admin' => 'boolean',
            'notification_preferences' => 'array',
        ];
    }

    /**
     * Public URL of the user's avatar, when they have uploaded one.
     *
     * @return Attribute<string|null, never>
     */
    protected function avatar(): Attribute
    {
        return Attribute::get(
            fn (): ?string => $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null,
        );
    }

    /**
     * @return HasMany<OrganizationMembership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    /**
     * Memberships the user can currently use: their own membership and the
     * organization itself must both be active.
     *
     * @return HasMany<OrganizationMembership, $this>
     */
    public function activeMemberships(): HasMany
    {
        return $this->memberships()->where('status', MembershipStatus::Active);
    }

    /**
     * Every membership with its organization, loaded once per request. The
     * tenant middleware, the organization switcher and the post-login redirect
     * all read from this.
     *
     * @return Collection<int, OrganizationMembership>
     */
    public function membershipsWithOrganizations(): Collection
    {
        if (! $this->relationLoaded('memberships')) {
            $this->setRelation('memberships', $this->memberships()->with('organization')->orderBy('created_at')->get());
        }

        return $this->memberships;
    }

    /**
     * Memberships the user can open right now.
     *
     * @return Collection<int, OrganizationMembership>
     */
    public function usableMemberships(): Collection
    {
        return $this->membershipsWithOrganizations()
            ->filter(fn (OrganizationMembership $membership): bool => $membership->isActive() && $membership->organization->isActive())
            ->values();
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function lastOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'last_organization_id');
    }

    /**
     * Notifications use FlowPilot's own model, which knows its organization.
     *
     * @return MorphMany<Notification, $this>
     */
    public function notifications(): MorphMany
    {
        return $this->morphMany(Notification::class, 'notifiable')->latest();
    }

    public function isPlatformAdmin(): bool
    {
        return $this->is_platform_admin;
    }
}
