<?php

namespace App\Http\Middleware;

use App\Enums\Permission;
use App\Models\Approval;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * Only what the shell needs on every page is shared here. The user is sent
     * as an explicit shape so loaded relations never leak into the page.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user instanceof User ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar,
                    'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                    'two_factor_enabled' => $user->two_factor_confirmed_at !== null,
                    'is_platform_admin' => $user->isPlatformAdmin(),
                    'created_at' => $user->created_at?->toIso8601String(),
                    'updated_at' => $user->updated_at?->toIso8601String(),
                ] : null,
            ],
            // Lazy: the tenant middleware runs after this one, so the current
            // organization is only known once the page is rendered.
            'organization' => fn (): ?array => $this->currentOrganization(),
            'unreadNotifications' => fn (): int => $this->unreadNotifications($user),
            'pendingApprovals' => fn (): int => $this->pendingApprovals($user),
            'organizations' => fn (): array => $user instanceof User
                ? $user->usableMemberships()
                    ->map(fn (OrganizationMembership $membership): array => [
                        'id' => $membership->organization->id,
                        'name' => $membership->organization->name,
                        'slug' => $membership->organization->slug,
                        'logo' => $membership->organization->logoUrl(),
                        'role_label' => $membership->role->label(),
                    ])
                    ->all()
                : [],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * Requests waiting on the signed-in member's decision, for the sidebar badge.
     */
    private function pendingApprovals(mixed $user): int
    {
        $tenancy = app(Tenancy::class);
        $membership = $tenancy->membership();

        if (! $user instanceof User || $membership === null) {
            return 0;
        }

        if (! $membership->allows(Permission::ApprovalsApprove) && ! $membership->allows(Permission::ApprovalsReject)) {
            return 0;
        }

        return Approval::query()
            ->waitingOn($user, $membership)
            ->where(fn ($query) => $query->whereNull('requester_id')->orWhere('requester_id', '!=', $user->id))
            ->count();
    }

    private function unreadNotifications(mixed $user): int
    {
        $organization = app(Tenancy::class)->current();

        if (! $user instanceof User || $organization === null) {
            return 0;
        }

        return $user->notifications()
            ->where('organization_id', $organization->id)
            ->whereNull('read_at')
            ->count();
    }

    /**
     * @return array{id: string, name: string, slug: string, logo: string|null, timezone: string, currency: string, date_format: string, role: string, role_label: string, permissions: list<string>}|null
     */
    private function currentOrganization(): ?array
    {
        $tenancy = app(Tenancy::class);
        $organization = $tenancy->current();
        $membership = $tenancy->membership();

        if ($organization === null || $membership === null) {
            return null;
        }

        return [
            'id' => $organization->id,
            'name' => $organization->name,
            'slug' => $organization->slug,
            'logo' => $organization->logoUrl(),
            'timezone' => $organization->timezone,
            'currency' => $organization->currency,
            'date_format' => $organization->date_format,
            'role' => $membership->role->value,
            'role_label' => $membership->role->label(),
            'permissions' => $membership->permissionValues(),
        ];
    }
}
