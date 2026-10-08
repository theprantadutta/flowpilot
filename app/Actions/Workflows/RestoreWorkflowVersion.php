<?php

namespace App\Actions\Workflows;

use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowVersion;
use App\Support\Activity\ActivityLogger;

/**
 * Copies a published version back into the draft, to roll back or to start
 * again from a known-good point. Nothing changes for runs until it is published.
 */
class RestoreWorkflowVersion
{
    public function __construct(private readonly ActivityLogger $activity) {}

    public function handle(Workflow $workflow, WorkflowVersion $version, User $actor): Workflow
    {
        $workflow->forceFill([
            'draft_definition' => [...$version->definition, 'trigger' => $version->trigger_type],
            'updated_by' => $actor->id,
        ])->save();

        $this->activity->log('workflow.version_restored', $workflow, [
            'name' => $workflow->name,
            'version' => $version->version,
        ], actor: $actor);

        return $workflow;
    }
}
