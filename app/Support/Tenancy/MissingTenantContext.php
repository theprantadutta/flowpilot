<?php

namespace App\Support\Tenancy;

use RuntimeException;

/**
 * Thrown when tenant-owned data is touched without a current organization.
 *
 * Failing loudly is deliberate: the alternative is a query that silently spans
 * every tenant.
 */
class MissingTenantContext extends RuntimeException
{
    public function __construct(?string $model = null)
    {
        parent::__construct(
            $model
                ? "No current organization is set while accessing [{$model}]. Wrap the work in Tenancy::run() or opt out explicitly with withoutOrganizationScope()."
                : 'No current organization is set.',
        );
    }
}
