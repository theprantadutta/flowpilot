<?php

namespace App\Actions\Workflows;

use App\Enums\Feature;
use App\Enums\NodeType;
use App\Enums\WorkflowStatus;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowVersion;
use App\Support\Activity\ActivityLogger;
use App\Support\Billing\Entitlements;
use App\Support\Tenancy\Tenancy;
use App\Workflows\Definition\DefinitionValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turns the draft into a new immutable version and makes it the one that
 * runs. Runs already under way keep the version they started on.
 */
class PublishWorkflow
{
    public function __construct(
        private readonly DefinitionValidator $validator,
        private readonly ActivityLogger $activity,
        private readonly Tenancy $tenancy,
        private readonly Entitlements $entitlements,
    ) {}

    /**
     * @throws ValidationException When the draft has problems.
     */
    public function handle(Workflow $workflow, User $actor, ?string $notes = null): WorkflowVersion
    {
        $definition = $workflow->draft();
        $trigger = $workflow->draftTrigger();
        $issues = $this->validator->validate($definition, $trigger, $this->tenancy->currentOrFail());

        if ($issues !== []) {
            throw ValidationException::withMessages([
                'definition' => array_map(fn (array $issue): string => $issue['message'], $issues),
            ]);
        }

        $types = array_column($definition->nodes, 'type');

        if (in_array(NodeType::Approval->value, $types, true)) {
            $this->entitlements->ensure(Feature::Approvals, 'definition');
        }

        if (in_array(NodeType::Webhook->value, $types, true)) {
            $this->entitlements->ensure(Feature::Webhooks, 'definition');
        }

        return DB::transaction(function () use ($workflow, $actor, $notes, $definition, $trigger): WorkflowVersion {
            /** @var Workflow $locked */
            $locked = Workflow::query()->whereKey($workflow->id)->lockForUpdate()->firstOrFail();
            $current = $locked->current_version_id ? WorkflowVersion::query()->find($locked->current_version_id) : null;
            $checksum = $definition->checksum();

            // Publishing an unchanged draft just makes sure it is live.
            if ($current !== null && $current->checksum === $checksum && $current->trigger_type === $trigger) {
                $version = $current;
            } else {
                $version = WorkflowVersion::query()->create([
                    'workflow_id' => $locked->id,
                    'version' => (int) WorkflowVersion::query()->where('workflow_id', $locked->id)->max('version') + 1,
                    'trigger_type' => $trigger,
                    'definition' => $definition->toArray(),
                    'checksum' => $checksum,
                    'notes' => $notes,
                    'published_by' => $actor->id,
                    'published_at' => now(),
                ]);
            }

            $locked->forceFill([
                'current_version_id' => $version->id,
                'trigger_type' => $trigger,
                'status' => WorkflowStatus::Active,
                'published_at' => now(),
                'updated_by' => $actor->id,
            ])->save();

            $workflow->setRawAttributes($locked->getAttributes(), true);
            $workflow->setRelation('currentVersion', $version);

            $this->activity->log('workflow.published', $workflow, [
                'name' => $workflow->name,
                'version' => $version->version,
                'notes' => $notes,
            ], actor: $actor);

            return $version;
        });
    }
}
