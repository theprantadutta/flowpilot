<?php

namespace App\Workflows\Support;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Model;

/**
 * Absolute links to records, for messages and webhooks sent from runs (which
 * happen on the queue, outside any request).
 */
class RecordLinks
{
    /**
     * Route and parameter name by morph alias.
     */
    private const array ROUTES = [
        'task' => ['tasks.show', 'task'],
        'issue' => ['issues.show', 'issue'],
        'workflow_run' => ['workflow-runs.show', 'run'],
        'purchase_request' => ['purchase-requests.show', 'purchaseRequest'],
        'inventory_item' => ['inventory.items.show', 'item'],
        'approval' => ['approvals.show', 'approval'],
    ];

    public static function for(Model $record, Organization $organization): ?string
    {
        return self::forType($record->getMorphClass(), (string) $record->getKey(), $organization);
    }

    public static function forType(string $type, string $id, Organization $organization): ?string
    {
        if (! isset(self::ROUTES[$type])) {
            return null;
        }

        [$route, $parameter] = self::ROUTES[$type];

        return route($route, ['organization' => $organization->slug, $parameter => $id]);
    }
}
