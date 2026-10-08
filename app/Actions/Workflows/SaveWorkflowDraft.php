<?php

namespace App\Actions\Workflows;

use App\Models\User;
use App\Models\Workflow;
use App\Workflows\Definition\WorkflowDefinition;

/**
 * Saves the builder's canvas. Drafts may be unfinished; nothing runs until
 * the workflow is published, so this does not log every save.
 */
class SaveWorkflowDraft
{
    public function handle(Workflow $workflow, User $actor, WorkflowDefinition $definition, string $trigger): Workflow
    {
        $workflow->forceFill([
            'draft_definition' => [...$definition->toArray(), 'trigger' => $trigger],
            'updated_by' => $actor->id,
        ]);

        // Never-published workflows have no live trigger yet: keep them in step with the draft.
        if ($workflow->current_version_id === null) {
            $workflow->trigger_type = $trigger;
        }

        $workflow->save();

        return $workflow;
    }
}
