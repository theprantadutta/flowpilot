<?php

namespace App\Http\Requests\Organizations;

use App\Enums\CompanySize;
use App\Enums\Industry;
use App\Enums\NotificationType;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use App\Support\Currencies;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates one section of the organization settings. The section comes from
 * the route, so each form only accepts its own fields.
 */
class UpdateOrganizationSettingsRequest extends FormRequest
{
    public const array SECTIONS = ['general', 'regional', 'notifications', 'security', 'members'];

    public const array IDLE_TIMEOUTS = [0, 15, 30, 60, 120, 240, 480];

    public function authorize(): bool
    {
        return (bool) $this->user()?->can(Permission::SettingsManage->value);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return match ($this->section()) {
            'general' => [
                'name' => ['required', 'string', 'min:2', 'max:120'],
                'website' => ['nullable', 'url:http,https', 'max:255'],
                'industry' => ['nullable', Rule::enum(Industry::class)],
                'company_size' => ['nullable', Rule::enum(CompanySize::class)],
                'contact_email' => ['nullable', 'email:rfc', 'max:255'],
                'contact_phone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+()\-.\s]+$/'],
                'address' => ['nullable', 'string', 'max:255'],
            ],
            'regional' => [
                'timezone' => ['required', 'string', 'timezone:all'],
                'currency' => ['required', 'string', Rule::in(Currencies::codes())],
                'date_format' => ['required', 'string', Rule::in(array_keys(config()->array('flowpilot.date_formats')))],
            ],
            'notifications' => [
                'email' => ['present', 'array'],
                'email.*' => ['boolean'],
            ],
            'security' => [
                'require_two_factor' => ['required', 'boolean', $this->actorHasTwoFactor()],
                'idle_timeout_minutes' => ['required', 'integer', Rule::in(self::IDLE_TIMEOUTS)],
            ],
            'members' => [
                'default_role' => ['required', Rule::enum(Role::class)->except([Role::Owner])],
                'allow_member_invites' => ['required', 'boolean'],
            ],
            default => [],
        };
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'contact_phone.regex' => 'Use digits, spaces and + ( ) - only.',
            'website.url' => 'Enter a full web address, starting with https://.',
        ];
    }

    public function section(): string
    {
        $section = $this->route('section');

        return is_string($section) && in_array($section, self::SECTIONS, true) ? $section : 'general';
    }

    /**
     * Requiring two-factor while not using it yourself would lock you out.
     */
    private function actorHasTwoFactor(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $user = $this->user();

            if ($value && $user instanceof User && $user->two_factor_confirmed_at === null) {
                $fail('Turn on two-factor authentication for your own account first, so you are not locked out.');
            }
        };
    }

    /**
     * The validated input split into organization columns and settings paths.
     *
     * @return array{columns: array<string, mixed>, settings: array<string, mixed>}
     */
    public function changes(): array
    {
        $data = $this->validated();

        return match ($this->section()) {
            'general', 'regional' => ['columns' => $data, 'settings' => []],
            'notifications' => ['columns' => [], 'settings' => ['notifications.email' => $this->emailDefaults()]],
            'security' => ['columns' => [], 'settings' => [
                'security.require_two_factor' => $this->boolean('require_two_factor'),
                'security.idle_timeout_minutes' => $this->integer('idle_timeout_minutes'),
            ]],
            'members' => ['columns' => [], 'settings' => [
                'members.default_role' => $this->string('default_role')->toString(),
                'members.allow_member_invites' => $this->boolean('allow_member_invites'),
            ]],
            default => ['columns' => [], 'settings' => []],
        };
    }

    /**
     * Only known notification types are kept, and only where they differ from the type's default.
     *
     * @return array<string, bool>
     */
    private function emailDefaults(): array
    {
        $submitted = $this->input('email', []);
        $defaults = [];

        foreach (NotificationType::cases() as $type) {
            if (is_array($submitted) && array_key_exists($type->value, $submitted)) {
                $wanted = filter_var($submitted[$type->value], FILTER_VALIDATE_BOOLEAN);

                if ($wanted !== $type->emailByDefault()) {
                    $defaults[$type->value] = $wanted;
                }
            }
        }

        return $defaults;
    }
}
