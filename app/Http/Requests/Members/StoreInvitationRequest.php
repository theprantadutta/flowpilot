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
        return (bool) $this->user()?->can(Permission::MembersInvite->value);
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

            if ($target && $actorRole && ! $actorRole->canAssign($target)) {
                $fail("Your role cannot invite someone as {$target->label()}.");
            }
        };
    }

    public function role(): Role
    {
        return Role::from($this->validated('role'));
    }
}
