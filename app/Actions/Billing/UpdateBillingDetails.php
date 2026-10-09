<?php

namespace App\Actions\Billing;

use App\Models\BillingCustomer;
use App\Models\User;
use App\Support\Activity\ActivityLogger;

class UpdateBillingDetails
{
    public function __construct(private readonly ActivityLogger $activity) {}

    /**
     * @param  array{legal_name?: string|null, email?: string|null, address?: string|null, country?: string|null, tax_id?: string|null}  $details
     */
    public function handle(User $actor, array $details): BillingCustomer
    {
        $customer = BillingCustomer::query()->firstOrNew([]);
        $customer->fill($details);
        $changed = array_keys($customer->getDirty());
        $customer->save();

        if ($changed !== []) {
            $this->activity->log('billing.details_updated', $customer, ['fields' => $changed], actor: $actor);
        }

        return $customer;
    }
}
