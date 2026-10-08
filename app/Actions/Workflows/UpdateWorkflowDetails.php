<?php

namespace App\Actions\Workflows;

use App\Models\User;
use App\Models\Workflow;
use App\Support\Activity\ActivityLogger;

class UpdateWorkflowDetails
{
    public function __construct(private readonly ActivityLogger $activity) {}

    /**
     * @param  array{name?: string, description?: string|null}  $attributes
     */
    public function handle(Workflow $workflow, User $actor, array $attributes): Workflow
    {
        $before = ['name' => $workflow->name, 'description' => $workflow->description];

        $workflow->fill([...$attributes, 'updated_by' => $actor->id])->save();

        $changes = ActivityLogger::diff($before, ['name' => $workflow->name, 'description' => $workflow->description]);

        if ($changes !== []) {
            $this->activity->log('workflow.updated', $workflow, [
                'name' => $workflow->name,
                'changes' => $changes,
            ], actor: $actor);
        }

        return $workflow;
    }
}
