<?php

namespace App\Http\Middleware;

use App\Models\OrganizationMembership;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the organization named in the URL, proves the signed-in user is an
 * active member of it, and makes it the tenant for the rest of the request.
 *
 * Runs before route model binding, so every bound tenant model is already
 * scoped: an id belonging to another organization simply does not resolve.
 */
class SetCurrentOrganization
{
    public function __construct(private readonly Tenancy $tenancy) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User $user */
        $user = $request->user();
        $slug = (string) $request->route('organization');

        $membership = $user->membershipsWithOrganizations()
            ->first(fn (OrganizationMembership $membership): bool => $membership->organization->slug === $slug);

        // Unknown organization and "not a member" look identical from outside.
        abort_if($membership === null, 404);

        abort_unless($membership->isActive(), 403, 'Your access to this organization has been suspended.');
        abort_unless($membership->organization->isActive(), 403, 'This organization is suspended.');

        $organization = $membership->organization;

        $this->tenancy->set($organization, $membership);

        URL::defaults(['organization' => $organization->slug]);
        $request->route()?->forgetParameter('organization');

        $this->recordPresence($user, $membership);

        return $next($request);
    }

    /**
     * Remember where the user last worked, without writing on every request.
     */
    private function recordPresence(User $user, OrganizationMembership $membership): void
    {
        if ($membership->last_active_at === null || $membership->last_active_at->lt(now()->subMinutes(10))) {
            $membership->forceFill(['last_active_at' => now()])->saveQuietly();
        }

        if ($user->last_organization_id !== $membership->organization_id) {
            $user->forceFill(['last_organization_id' => $membership->organization_id])->saveQuietly();
        }
    }
}
