<?php

namespace App\Http\Requests\Members;

use App\Enums\Permission;
use App\Enums\Role;
use App\Support\Tenancy\Tenancy;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can(Permission::MembersInvite->value)
            || $this->invitingAsMember();
    }

    /**
     * When the organization lets every member invite colleagues, members without
     * the invite permission may still invite, but only as Employee.
     */
    private function invitingAsMember(): bool
    {
        $organization = app(Tenancy::class)->current();

        return $organization !== null
            && (bool) $organization->setting('members.allow_member_invites')
            && app(Tenancy::class)->membership()?->isActive() === true;
    }

    /**
     * @return array<string, array<int, ValidationRule|Closure|string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'role' => ['required', Rule::enum(Role::class), $this->assignableRole()],
            'department' => ['nullable', 'string', 'max:80'],
        ];
    }

    /**
     * A member can only hand out roles below their own.
     */
    private function assignableRole(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $target = is_string($value) ? Role::tryFrom($value) : null;
            $actorRole = app(Tenancy::class)->membership()?->role;

            if ($target === null || $actorRole === null) {
                return;
            }

            $canInviteFreely = (bool) $this->user()?->can(Permission::MembersInvite->value);

            if (! $canInviteFreely) {
                if ($target !== Role::Employee) {
                    $fail('You can invite colleagues as Employee. Ask an admin to give them a different role.');
                }

                return;
            }

            if (! $actorRole->canAssign($target)) {
                $fail("Your role cannot invite someone as {$target->label()}.");
            }
        };
    }

    public function role(): Role
    {
        return Role::from($this->validated('role'));
    }
}
