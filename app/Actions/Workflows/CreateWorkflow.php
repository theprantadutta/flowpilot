<?php

namespace App\Actions\Workflows;

use App\Enums\WorkflowStatus;
use App\Models\User;
use App\Models\Workflow;
use App\Support\Activity\ActivityLogger;
use App\Workflows\Templates\WorkflowTemplates;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreateWorkflow
{
    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly WorkflowTemplates $templates,
    ) {}

    /**
     * Create a draft workflow, either from a template or with just a trigger.
     */
    public function handle(User $actor, string $name, ?string $description, string $trigger, ?string $templateKey = null): Workflow
    {
        $template = $templateKey !== null && $templateKey !== 'blank' ? $this->templates->find($templateKey) : null;

        if ($templateKey !== null && $templateKey !== 'blank' && $template === null) {
            throw new InvalidArgumentException("Unknown workflow template [{$templateKey}].");
        }

        $trigger = $template['trigger'] ?? $trigger;

        return DB::transaction(function () use ($actor, $name, $description, $trigger, $template): Workflow {
            $workflow = Workflow::query()->create([
                'name' => $name,
                'description' => $description ?? $template['description'] ?? null,
                'status' => WorkflowStatus::Draft,
                'trigger_type' => $trigger,
                'draft_definition' => [...($template['definition'] ?? $this->templates->starter($trigger)), 'trigger' => $trigger],
                'template' => $template['key'] ?? null,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $this->activity->log('workflow.created', $workflow, [
                'name' => $workflow->name,
                'template' => $template['name'] ?? null,
            ], actor: $actor);

            return $workflow;
        });
    }
}
