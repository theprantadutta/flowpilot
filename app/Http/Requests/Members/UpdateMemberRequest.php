<?php

namespace App\Http\Requests\Members;

use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Models\OrganizationMembership;
use App\Support\Tenancy\Tenancy;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        $member = $this->route('member');

        return $member instanceof OrganizationMembership
            && (bool) $this->user()?->can('update', $member);
    }

    /**
     * @return array<string, array<int, ValidationRule|Closure|string>>
     */
    public function rules(): array
    {
        return [
            'role' => ['sometimes', 'required', Rule::enum(Role::class), $this->assignableRole()],
            'status' => ['sometimes', 'required', Rule::enum(MembershipStatus::class)],
            'department' => ['sometimes', 'nullable', 'string', 'max:80'],
            'job_title' => ['sometimes', 'nullable', 'string', 'max:120'],
        ];
    }

    private function assignableRole(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $target = is_string($value) ? Role::tryFrom($value) : null;
            $actorRole = app(Tenancy::class)->membership()?->role;

            if ($target && $actorRole && ! $actorRole->canAssign($target)) {
                $fail("Your role cannot assign the {$target->label()} role.");
            }
        };
    }

    /**
     * The validated changes with enum values resolved.
     *
     * @return array{role?: Role, status?: MembershipStatus, department?: string|null, job_title?: string|null}
     */
    public function changes(): array
    {
        $changes = $this->safe()->only(['department', 'job_title']);

        if ($this->has('role')) {
            $changes['role'] = Role::from($this->validated('role'));
        }

        if ($this->has('status')) {
            $changes['status'] = MembershipStatus::from($this->validated('status'));
        }

        return $changes;
    }
}
