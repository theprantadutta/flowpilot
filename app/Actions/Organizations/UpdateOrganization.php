<?php

namespace App\Actions\Organizations;

use App\Models\Organization;
use App\Models\User;
use App\Support\Activity\ActivityLogger;
use Illuminate\Support\Arr;

class UpdateOrganization
{
    public function __construct(private readonly ActivityLogger $activity) {}

    /**
     * Apply a settings change and record exactly what changed.
     *
     * @param  string  $section  The settings section, used in the audit trail (general, regional, …).
     * @param  array<string, mixed>  $columns  Organization columns to update.
     * @param  array<string, mixed>  $settings  Settings to merge, keyed by dot path (e.g. "security.require_two_factor").
     */
    public function handle(Organization $organization, User $actor, string $section, array $columns = [], array $settings = []): Organization
    {
        $before = [];
        $after = [];

        foreach ($columns as $column => $value) {
            $before[$column] = $this->comparable($organization->getAttribute($column));
            $after[$column] = $this->comparable($value);
        }

        $stored = $organization->settings ?? [];

        foreach ($settings as $path => $value) {
            $before[$path] = $organization->setting($path);
            $after[$path] = $value;
            Arr::set($stored, $path, $value);
        }

        $organization->fill($columns);

        if ($settings !== []) {
            $organization->settings = $stored;
        }

        $organization->save();

        $changes = ActivityLogger::diff($before, $after);

        if ($changes !== []) {
            $this->activity->log('settings.updated', $organization, [
                'section' => $section,
                'changes' => $changes,
            ], actor: $actor);
        }

        return $organization;
    }

    private function comparable(mixed $value): mixed
    {
        return $value instanceof \BackedEnum ? $value->value : $value;
    }
}
