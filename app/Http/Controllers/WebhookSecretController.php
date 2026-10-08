<?php

namespace App\Http\Controllers;

use App\Actions\Organizations\RotateWebhookSecret;
use App\Enums\Permission;
use App\Models\User;
use App\Support\Activity\ActivityLogger;
use App\Support\Tenancy\Tenancy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * The key receivers use to check that webhooks really came from FlowPilot.
 * It is only ever shown on request, to people who manage settings, and every
 * reveal is recorded.
 */
class WebhookSecretController extends Controller
{
    public function __construct(private readonly Tenancy $tenancy) {}

    public function show(Request $request, ActivityLogger $activity): JsonResponse
    {
        Gate::authorize(Permission::SettingsManage->value);

        $organization = $this->tenancy->currentOrFail();
        $secret = $organization->webhookSecret();

        $activity->log('settings.webhook_secret_viewed', $organization, organization: $organization);

        return response()->json(['secret' => $secret])->header('Cache-Control', 'no-store');
    }

    public function store(Request $request, RotateWebhookSecret $rotate): RedirectResponse
    {
        Gate::authorize(Permission::SettingsManage->value);

        /** @var User $user */
        $user = $request->user();

        $rotate->handle($this->tenancy->currentOrFail(), $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'New signing secret created. Update your receivers to use it.']);

        return back();
    }
}
