<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the current organization's security settings: members must have
 * two-factor authentication when the organization requires it, and are signed
 * out after the organization's idle timeout.
 */
class EnforceOrganizationSecurity
{
    public function __construct(private readonly Tenancy $tenancy) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $organization = $this->tenancy->currentOrFail();

        /** @var User $user */
        $user = $request->user();

        $timeout = (int) $organization->setting('security.idle_timeout_minutes', 0);
        $activityKey = "organization_activity.{$organization->id}";
        $lastActivity = $request->session()->get($activityKey);

        if ($timeout > 0 && is_int($lastActivity) && now()->getTimestamp() - $lastActivity > $timeout * 60) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return to_route('login')->with('status', "You were signed out after {$timeout} minutes without activity, as {$organization->name} requires. Sign in again to continue.");
        }

        $request->session()->put($activityKey, now()->getTimestamp());

        if ($organization->setting('security.require_two_factor') && $user->two_factor_confirmed_at === null) {
            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => "{$organization->name} requires two-factor authentication. Turn it on to continue.",
            ]);

            return to_route('security.edit');
        }

        return $next($request);
    }
}
