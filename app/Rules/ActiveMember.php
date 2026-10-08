<?php

namespace App\Rules;

use App\Enums\MembershipStatus;
use App\Models\OrganizationMembership;
use App\Support\Tenancy\Tenancy;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The value must be the user id of an active member of the current
 * organization. Stops work being assigned to outsiders or suspended people.
 */
class ActiveMember implements ValidationRule
{
    public function __construct(private readonly string $message = 'Choose someone who is an active member of this organization.') {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_numeric($value)) {
            $fail($this->message);

            return;
        }

        $isMember = OrganizationMembership::query()
            ->where('organization_id', app(Tenancy::class)->currentOrFail()->id)
            ->where('user_id', (int) $value)
            ->where('status', MembershipStatus::Active)
            ->exists();

        if (! $isMember) {
            $fail($this->message);
        }
    }
}
