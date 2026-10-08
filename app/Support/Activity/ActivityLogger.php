<?php

namespace App\Support\Activity;

use App\Models\ActivityLog;
use App\Models\Organization;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Writes entries to the activity log. Called from the action that performs a
 * change, so the feed and the audit trail describe intent ("role changed"),
 * not raw column writes.
 */
class ActivityLogger
{
    public function __construct(private readonly Tenancy $tenancy) {}

    /**
     * @param  string  $action  Dot-separated event name, e.g. "member.role_changed".
     * @param  Model|null  $subject  The record the event is about.
     * @param  array<string, mixed>  $properties  Extra detail; put before/after values under "changes".
     * @param  Model|null  $context  A parent record the event should also be listed under.
     * @param  User|null  $actor  Defaults to the signed-in user; pass null with a system/ai actor type for automation.
     */
    public function log(
        string $action,
        ?Model $subject = null,
        array $properties = [],
        ?Model $context = null,
        ?User $actor = null,
        string $actorType = 'user',
        ?Organization $organization = null,
    ): ActivityLog {
        $request = $this->currentRequest();
        $actor ??= $actorType === 'user' ? $this->authenticatedUser($request) : null;

        if ($actor === null && $actorType === 'user') {
            $actorType = 'system';
        }

        return ActivityLog::query()->create([
            'organization_id' => $organization->id ?? $this->tenancy->id(),
            'actor_id' => $actor?->id,
            'actor_type' => $actorType,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject ? (string) $subject->getKey() : null,
            'subject_label' => $subject ? $this->labelFor($subject) : null,
            'context_type' => $context?->getMorphClass(),
            'context_id' => $context ? (string) $context->getKey() : null,
            'properties' => $properties ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? Str::limit((string) $request->userAgent(), 250, '') : null,
        ]);
    }

    /**
     * Build a before/after map for the attributes that actually changed.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, array{from: mixed, to: mixed}>
     */
    public static function diff(array $before, array $after): array
    {
        $changes = [];

        foreach ($after as $key => $value) {
            $previous = $before[$key] ?? null;

            if ($previous != $value) {
                $changes[$key] = ['from' => $previous, 'to' => $value];
            }
        }

        return $changes;
    }

    private function labelFor(Model $subject): ?string
    {
        $attributes = $subject->getAttributes();

        foreach (['name', 'title', 'email', 'reference'] as $attribute) {
            $value = $attributes[$attribute] ?? null;

            if (is_string($value) && $value !== '') {
                return Str::limit($value, 250, '');
            }
        }

        return null;
    }

    private function currentRequest(): ?Request
    {
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return null;
        }

        return app()->bound('request') ? request() : null;
    }

    private function authenticatedUser(?Request $request): ?User
    {
        $user = $request?->user() ?? auth()->user();

        return $user instanceof User ? $user : null;
    }
}
