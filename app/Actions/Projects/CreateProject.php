<?php

namespace App\Actions\Projects;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use App\Support\Activity\ActivityLogger;
use Illuminate\Support\Facades\DB;

class CreateProject
{
    public function __construct(private readonly ActivityLogger $activity) {}

    /**
     * @param  array<string, mixed>  $attributes  Validated project attributes.
     * @param  list<int>  $memberIds
     */
    public function handle(User $actor, array $attributes, array $memberIds = []): Project
    {
        return DB::transaction(function () use ($actor, $attributes, $memberIds): Project {
            $project = Project::query()->create([
                ...$attributes,
                'owner_id' => $attributes['owner_id'] ?? $actor->id,
                'completed_at' => ($attributes['status'] ?? null) === ProjectStatus::Completed->value ? now() : null,
            ]);

            $project->members()->sync(array_values(array_unique([...$memberIds, $project->owner_id ?? $actor->id])));

            $this->activity->log('project.created', $project, ['name' => $project->name], actor: $actor);

            return $project;
        });
    }
}
