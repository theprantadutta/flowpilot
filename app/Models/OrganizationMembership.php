<?php

namespace App\Models;

use App\Enums\MembershipStatus;
use App\Enums\Permission;
use App\Enums\Role;
use Carbon\CarbonImmutable;
use Database\Factories\OrganizationMembershipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's place in one organization. Deliberately not auto-scoped to the
 * current organization: it is what the tenant context is resolved from.
 *
 * @property string $id
 * @property string $organization_id
 * @property int $user_id
 * @property Role $role
 * @property MembershipStatus $status
 * @property string|null $department
 * @property string|null $job_title
 * @property int|null $invited_by
 * @property CarbonImmutable|null $invited_at
 * @property CarbonImmutable|null $joined_at
 * @property CarbonImmutable|null $last_active_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Organization $organization
 * @property-read User $user
 */
#[Fillable([
    'organization_id', 'user_id', 'role', 'status', 'department', 'job_title',
    'invited_by', 'invited_at', 'joined_at', 'last_active_at',
])]
class OrganizationMembership extends Model
{
    /** @use HasFactory<OrganizationMembershipFactory> */
    use HasFactory, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
        'department' => null,
        'job_title' => null,
        'invited_by' => null,
        'invited_at' => null,
        'joined_at' => null,
        'last_active_at' => null,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'status' => MembershipStatus::class,
            'invited_at' => 'datetime',
            'joined_at' => 'datetime',
            'last_active_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isActive(): bool
    {
        return $this->status === MembershipStatus::Active;
    }

    /**
     * The single place a member's permissions are resolved. Custom roles would
     * plug in here.
     */
    public function allows(Permission $permission): bool
    {
        return $this->isActive() && $this->role->allows($permission);
    }

    /**
     * @return list<string>
     */
    public function permissionValues(): array
    {
        if (! $this->isActive()) {
            return [];
        }

        return array_map(fn (Permission $permission): string => $permission->value, $this->role->permissions());
    }
}
