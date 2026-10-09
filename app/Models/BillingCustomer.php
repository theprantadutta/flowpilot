<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Who invoices are addressed to. Kept apart from the organization's profile
 * because finance details often differ from the public name and contact.
 *
 * @property string $id
 * @property string $organization_id
 * @property string|null $legal_name
 * @property string|null $email
 * @property string|null $address
 * @property string|null $country
 * @property string|null $tax_id
 * @property string|null $provider
 * @property string|null $provider_customer_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['legal_name', 'email', 'address', 'country', 'tax_id', 'provider', 'provider_customer_id'])]
class BillingCustomer extends Model
{
    use BelongsToOrganization, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'legal_name' => null,
        'email' => null,
        'address' => null,
        'country' => null,
        'tax_id' => null,
        'provider' => null,
        'provider_customer_id' => null,
    ];
}
