<?php

namespace App\Models;

use App\Enums\CompanySize;
use App\Enums\Industry;
use App\Enums\MembershipStatus;
use App\Enums\OrganizationStatus;
use App\Enums\UseCase;
use Carbon\CarbonImmutable;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property int $owner_id
 * @property OrganizationStatus $status
 * @property string|null $logo_path
 * @property string|null $website
 * @property Industry|null $industry
 * @property CompanySize|null $company_size
 * @property UseCase|null $primary_use_case
 * @property string $timezone
 * @property string $locale
 * @property string $currency
 * @property string $date_format
 * @property string|null $contact_email
 * @property string|null $contact_phone
 * @property string|null $address
 * @property array<string, mixed>|null $settings
 * @property CarbonImmutable|null $onboarded_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'name', 'slug', 'owner_id', 'status', 'logo_path', 'website', 'industry', 'company_size',
    'primary_use_case', 'timezone', 'locale', 'currency', 'date_format', 'contact_email',
    'contact_phone', 'address', 'settings', 'onboarded_at',
])]
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
        'timezone' => 'UTC',
        'locale' => 'en',
        'currency' => 'USD',
        'date_format' => 'M j, Y',
        'logo_path' => null,
        'website' => null,
        'industry' => null,
        'company_size' => null,
        'primary_use_case' => null,
        'contact_email' => null,
        'contact_phone' => null,
        'address' => null,
        'settings' => null,
        'onboarded_at' => null,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrganizationStatus::class,
            'industry' => Industry::class,
            'company_size' => CompanySize::class,
            'primary_use_case' => UseCase::class,
            'settings' => 'array',
            'onboarded_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return HasMany<OrganizationMembership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    /**
     * @return HasMany<OrganizationMembership, $this>
     */
    public function activeMemberships(): HasMany
    {
        return $this->memberships()->where('status', MembershipStatus::Active);
    }

    /**
     * @return HasMany<Invitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    public function isActive(): bool
    {
        return $this->status === OrganizationStatus::Active;
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    /**
     * Read a setting, falling back to the application default for that key.
     */
    public function setting(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->settings ?? [], $key)
            ?? Arr::get(config('flowpilot.organization_settings', []), $key, $default);
    }

    /**
     * All settings with defaults filled in.
     *
     * @return array<string, mixed>
     */
    public function resolvedSettings(): array
    {
        return array_replace_recursive(
            config('flowpilot.organization_settings', []),
            $this->settings ?? [],
        );
    }
}
