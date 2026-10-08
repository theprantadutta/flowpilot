<?php

namespace App\Support\Notifications;

use App\Enums\NotificationType;
use App\Models\Organization;
use App\Models\User;

/**
 * Decides whether a notification type is emailed to a member.
 *
 * The member's own choice wins, then the organization's default, then the
 * type's built-in default. In-app notifications are always recorded.
 */
class NotificationPreferences
{
    public function wantsEmail(User $user, NotificationType $type, ?Organization $organization): bool
    {
        $personal = $this->personal($user)[$type->value] ?? null;

        if (is_bool($personal)) {
            return $personal;
        }

        $organizationDefault = $organization ? $this->organizationDefaults($organization)[$type->value] ?? null : null;

        if (is_bool($organizationDefault)) {
            return $organizationDefault;
        }

        return $type->emailByDefault();
    }

    /**
     * The member's explicit choices, keyed by type.
     *
     * @return array<string, bool>
     */
    public function personal(User $user): array
    {
        return $this->booleanMap($user->notification_preferences['email'] ?? []);
    }

    /**
     * The organization's defaults, keyed by type.
     *
     * @return array<string, bool>
     */
    public function organizationDefaults(Organization $organization): array
    {
        return $this->booleanMap($organization->setting('notifications.email', []));
    }

    /**
     * @return array<string, bool>
     */
    private function booleanMap(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $map = [];

        foreach (NotificationType::cases() as $type) {
            if (array_key_exists($type->value, $values) && is_bool($values[$type->value])) {
                $map[$type->value] = $values[$type->value];
            }
        }

        return $map;
    }
}
