<?php

namespace App\Actions\Projects;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use App\Notifications\ProjectUpdatedNotification;
use App\Support\Activity\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class UpdateProject
{
    public function __construct(private readonly ActivityLogger $activity) {}

    /**
     * @param  array<string, mixed>  $attributes  Validated project attributes to change.
     * @param  list<int>|null  $memberIds  The full member list, or null to leave members alone.
     */
    public function handle(Project $project, User $actor, array $attributes, ?array $memberIds = null): Project
    {
        return DB::transaction(function () use ($project, $actor, $attributes, $memberIds): Project {
            $before = $this->snapshot($project);
            $previousStatus = $project->status;

            $project->fill($attributes);

            if ($project->isDirty('status')) {
                $project->completed_at = $project->status === ProjectStatus::Completed ? now() : null;
            }

            $project->save();

            if ($memberIds !== null) {
                $project->members()->sync(array_values(array_unique([...$memberIds, ...array_filter([$project->owner_id])])));
            }

            $changes = ActivityLogger::diff($before, $this->snapshot($project));

            if ($changes !== []) {
                $this->activity->log(
                    isset($changes['status']) ? 'project.status_changed' : 'project.updated',
                    $project,
                    ['name' => $project->name, 'changes' => $changes],
                    actor: $actor,
                );
            }

            if ($previousStatus !== $project->status) {
                $this->notifyMembers($project, $previousStatus, $actor);
            }

            return $project;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Project $project): array
    {
        return [
            'name' => $project->name,
            'description' => $project->description,
            'status' => $project->status->value,
            'priority' => $project->priority->value,
            'owner_id' => $project->owner_id,
            'start_date' => $project->start_date?->toDateString(),
            'due_date' => $project->due_date?->toDateString(),
            'budget_amount' => $project->budget_amount,
            'tags' => $project->tags,
        ];
    }

    private function notifyMembers(Project $project, ProjectStatus $from, User $actor): void
    {
        $recipients = $project->members()->where('users.id', '!=', $actor->id)->get();

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, ProjectUpdatedNotification::statusChanged($project, $from, $actor));
        }
    }
}
