<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Support\Search\Contains;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Every account, for support: which organizations it belongs to, whether it
 * is verified and protected by two-factor, and when it was last active.
 */
class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $q = $request->string('q')->trim()->limit(80, '')->toString();

        $users = User::query()
            ->with('memberships.organization:id,name,slug')
            ->withMax('memberships', 'last_active_at')
            ->when($q !== '', fn (Builder $query) => Contains::any($query, ['name', 'email'], $q))
            ->when($request->boolean('admins'), fn (Builder $query) => $query->where('is_platform_admin', true))
            ->latest()
            ->paginate(30)
            ->withQueryString()
            ->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'verified' => $user->email_verified_at !== null,
                'two_factor' => $user->two_factor_confirmed_at !== null,
                'is_platform_admin' => $user->isPlatformAdmin(),
                'organizations' => $user->memberships->sortBy('created_at')->values()->map(fn (OrganizationMembership $membership): array => [
                    'name' => $membership->organization->name,
                    'slug' => $membership->organization->slug,
                    'role' => $membership->role->label(),
                    'status' => $membership->status->value,
                ])->all(),
                // An aggregate comes back as the database's own text; timestamps are stored in UTC.
                'last_active_at' => is_string($last = $user->getAttribute('memberships_max_last_active_at')) ? CarbonImmutable::parse($last, 'UTC')->toIso8601String() : null,
                'created_at' => $user->created_at?->toIso8601String(),
            ]);

        return Inertia::render('platform/Users', [
            'users' => $users,
            'filters' => ['q' => $q, 'admins' => $request->boolean('admins')],
        ]);
    }

    /**
     * Send the account a password reset link, for someone locked out.
     */
    public function sendPasswordReset(Request $request, User $user): RedirectResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        $status = Password::sendResetLink(['email' => $user->email]);

        Log::info('Platform admin sent a password reset link', ['admin' => $admin->id, 'user' => $user->id, 'status' => $status]);

        Inertia::flash('toast', $status === Password::RESET_LINK_SENT
            ? ['type' => 'success', 'message' => "A reset link is on its way to {$user->email}."]
            : ['type' => 'error', 'message' => 'A link was sent to this account very recently. Try again in a minute.']);

        return back();
    }
}
