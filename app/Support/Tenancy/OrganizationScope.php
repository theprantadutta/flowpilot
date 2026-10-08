<?php

namespace App\Support\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Restricts every query on a tenant-owned model to the current organization.
 *
 * @template TModel of Model
 *
 * @implements Scope<TModel>
 */
class OrganizationScope implements Scope
{
    /**
     * @param  Builder<covariant TModel>  $builder
     * @param  TModel  $model
     */
    public function apply(Builder $builder, Model $model): void
    {
        $organizationId = app(Tenancy::class)->id()
            ?? throw new MissingTenantContext($model::class);

        $builder->where($model->qualifyColumn('organization_id'), $organizationId);
    }
}
