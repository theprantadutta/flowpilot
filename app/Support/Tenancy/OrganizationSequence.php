<?php

namespace App\Support\Tenancy;

use Illuminate\Support\Facades\DB;

/**
 * Hands out per-organization numbers (task 42, issue 7, request 1842) without
 * gaps or duplicates, even when two people create records at the same moment.
 */
class OrganizationSequence
{
    public function __construct(private readonly Tenancy $tenancy) {}

    public function next(string $name): int
    {
        $organizationId = $this->tenancy->currentOrFail()->id;

        return DB::transaction(function () use ($organizationId, $name): int {
            DB::table('organization_counters')->insertOrIgnore([
                'organization_id' => $organizationId,
                'name' => $name,
                'value' => 0,
            ]);

            $current = DB::table('organization_counters')
                ->where('organization_id', $organizationId)
                ->where('name', $name)
                ->lockForUpdate()
                ->value('value');

            $next = (int) $current + 1;

            DB::table('organization_counters')
                ->where('organization_id', $organizationId)
                ->where('name', $name)
                ->update(['value' => $next]);

            return $next;
        });
    }
}
