<?php

namespace App\Models\Concerns;

use App\Models\Organization;
use App\Support\Tenancy\MissingTenantContext;
use App\Support\Tenancy\OrganizationScope;
use App\Support\Tenancy\Tenancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Marks a model as owned by one organization.
 *
 * Queries are scoped to the current organization automatically and new records
 * are stamped with it, so feature code cannot forget either.
 *
 * @property string $organization_id
 *
 * @mixin Model
 */
trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope(new OrganizationScope);

        static::creating(function (Model $model): void {
            if (array_key_exists('organization_id', $model->getAttributes())) {
                return;
            }

            $model->setAttribute(
                'organization_id',
                app(Tenancy::class)->id() ?? throw new MissingTenantContext($model::class),
            );
        });
    }

    /**
     * Query across every organization. Only for platform administration and
     * system-wide sweeps; the call site should make the reason obvious.
     *
     * @return Builder<static>
     */
    public static function withoutOrganizationScope(): Builder
    {
        return static::query()->withoutGlobalScope(OrganizationScope::class);
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
