<?php

namespace App\Http\Controllers\Settings;

use App\Enums\NotificationType;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Notifications\NotificationPreferences;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A member's own choice of which notifications are also emailed. Their
 * choice overrides each organization's default.
 */
class NotificationPreferencesController extends Controller
{
    public function edit(Request $request, NotificationPreferences $preferences): Response
    {
        /** @var User $user */
        $user = $request->user();
        $organization = $user->lastOrganization;
        $personal = $preferences->personal($user);

        return Inertia::render('settings/Notifications', [
            'types' => NotificationType::options(),
            'email' => collect(NotificationType::cases())->mapWithKeys(fn (NotificationType $type): array => [
                $type->value => $preferences->wantsEmail($user, $type, $organization),
            ]),
            'customised' => array_keys($personal),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['present', 'array'],
            'email.*' => ['boolean'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $email = [];

        foreach (NotificationType::cases() as $type) {
            if (array_key_exists($type->value, $validated['email'])) {
                $email[$type->value] = (bool) $validated['email'][$type->value];
            }
        }

        $user->forceFill([
            'notification_preferences' => [...($user->notification_preferences ?? []), 'email' => $email],
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Notification settings saved.']);

        return back();
    }
}
