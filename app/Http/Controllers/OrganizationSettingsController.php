<?php

namespace App\Http\Controllers;

use App\Actions\Organizations\UpdateOrganization;
use App\Actions\Organizations\UpdateOrganizationLogo;
use App\Enums\CompanySize;
use App\Enums\Industry;
use App\Enums\NotificationType;
use App\Enums\Permission;
use App\Enums\Role;
use App\Http\Requests\Organizations\UpdateOrganizationLogoRequest;
use App\Http\Requests\Organizations\UpdateOrganizationSettingsRequest;
use App\Models\User;
use App\Support\Currencies;
use App\Support\Notifications\NotificationPreferences;
use App\Support\Tenancy\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationSettingsController extends Controller
{
    public function __construct(private readonly Tenancy $tenancy) {}

    public function show(Request $request, string $section = 'general'): Response
    {
        Gate::authorize(Permission::SettingsManage->value);

        abort_unless(in_array($section, UpdateOrganizationSettingsRequest::SECTIONS, true), 404);

        $organization = $this->tenancy->currentOrFail();

        /** @var User $user */
        $user = $request->user();

        return Inertia::render('organization-settings/'.ucfirst($section), [
            'section' => $section,
            'settings' => match ($section) {
                'general' => [
                    'name' => $organization->name,
                    'website' => $organization->website,
                    'industry' => $organization->industry?->value,
                    'company_size' => $organization->company_size?->value,
                    'contact_email' => $organization->contact_email,
                    'contact_phone' => $organization->contact_phone,
                    'address' => $organization->address,
                    'logo' => $organization->logoUrl(),
                ],
                'regional' => [
                    'timezone' => $organization->timezone,
                    'currency' => $organization->currency,
                    'date_format' => $organization->date_format,
                ],
                'notifications' => [
                    'email' => collect(NotificationType::cases())->mapWithKeys(fn (NotificationType $type): array => [
                        $type->value => app(NotificationPreferences::class)->organizationDefaults($organization)[$type->value] ?? $type->emailByDefault(),
                    ]),
                ],
                'security' => [
                    'require_two_factor' => (bool) $organization->setting('security.require_two_factor'),
                    'idle_timeout_minutes' => (int) $organization->setting('security.idle_timeout_minutes', 0),
                    'actor_has_two_factor' => $user->two_factor_confirmed_at !== null,
                    // Only the last characters, so the page itself never carries the secret.
                    'webhook_secret_hint' => $organization->webhook_secret !== null ? substr($organization->webhook_secret, -4) : null,
                ],
                'members' => [
                    'default_role' => (string) $organization->setting('members.default_role', 'employee'),
                    'allow_member_invites' => (bool) $organization->setting('members.allow_member_invites'),
                ],
            },
            'options' => match ($section) {
                'general' => [
                    'industries' => Industry::options(),
                    'companySizes' => CompanySize::options(),
                ],
                'regional' => [
                    'currencies' => Currencies::options(),
                    'dateFormats' => collect(config()->array('flowpilot.date_formats'))
                        ->map(fn (mixed $example, mixed $format): array => ['value' => (string) $format, 'label' => (string) $example])
                        ->values(),
                    'timezones' => timezone_identifiers_list(),
                ],
                'notifications' => ['types' => NotificationType::options()],
                'security' => ['idleTimeouts' => UpdateOrganizationSettingsRequest::IDLE_TIMEOUTS],
                'members' => [
                    'roles' => collect(Role::cases())
                        ->reject(fn (Role $role): bool => $role === Role::Owner)
                        ->map(fn (Role $role): array => ['value' => $role->value, 'label' => $role->label(), 'description' => $role->description()])
                        ->values(),
                ],
            },
        ]);
    }

    public function update(UpdateOrganizationSettingsRequest $request, UpdateOrganization $updateOrganization): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $changes = $request->changes();

        $updateOrganization->handle(
            $this->tenancy->currentOrFail(),
            $user,
            $request->section(),
            $changes['columns'],
            $changes['settings'],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Settings saved.']);

        return back();
    }

    public function updateLogo(UpdateOrganizationLogoRequest $request, UpdateOrganizationLogo $updateLogo): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $updateLogo->handle($this->tenancy->currentOrFail(), $user, $request->file('logo'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Logo updated.']);

        return back();
    }

    public function destroyLogo(Request $request, UpdateOrganizationLogo $updateLogo): RedirectResponse
    {
        Gate::authorize(Permission::SettingsManage->value);

        /** @var User $user */
        $user = $request->user();

        $updateLogo->remove($this->tenancy->currentOrFail(), $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Logo removed.']);

        return back();
    }
}
