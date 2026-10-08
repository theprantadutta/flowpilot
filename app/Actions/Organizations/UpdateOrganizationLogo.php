<?php

namespace App\Actions\Organizations;

use App\Models\Organization;
use App\Models\User;
use App\Support\Activity\ActivityLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UpdateOrganizationLogo
{
    public function __construct(private readonly ActivityLogger $activity) {}

    /**
     * Store a new logo under a random name and remove the previous one.
     */
    public function handle(Organization $organization, User $actor, UploadedFile $file): Organization
    {
        $previous = $organization->logo_path;

        $path = $file->storeAs(
            "organizations/{$organization->id}",
            'logo-'.Str::lower(Str::random(16)).'.'.$file->extension(),
            'public',
        );

        $organization->forceFill(['logo_path' => $path ?: null])->save();

        if ($previous && $previous !== $path) {
            Storage::disk('public')->delete($previous);
        }

        $this->activity->log('settings.logo_updated', $organization, actor: $actor);

        return $organization;
    }

    public function remove(Organization $organization, User $actor): Organization
    {
        if ($organization->logo_path) {
            Storage::disk('public')->delete($organization->logo_path);
            $organization->forceFill(['logo_path' => null])->save();

            $this->activity->log('settings.logo_removed', $organization, actor: $actor);
        }

        return $organization;
    }
}
