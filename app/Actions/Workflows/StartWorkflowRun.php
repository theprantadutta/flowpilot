<?php

namespace App\Actions\Workflows;

use App\Enums\MembershipStatus;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowRun;
use App\Support\Tenancy\Tenancy;
use App\Workflows\Engine\WorkflowEngine;
use App\Workflows\Triggers\TriggerRegistry;
use Illuminate\Validation\ValidationException;

/**
 * A person starts a run of a manually triggered workflow.
 */
class StartWorkflowRun
{
    public function __construct(
        private readonly WorkflowEngine $engine,
        private readonly TriggerRegistry $triggers,
        private readonly Tenancy $tenancy,
    ) {}

    /**
     * @param  array<string, mixed>  $values  What the person entered.
     * @param  string  $requestKey  Unique per form submission, so a double click starts one run.
     *
     * @throws ValidationException When the input is incomplete or invalid.
     */
    public function handle(Workflow $workflow, User $actor, array $values, string $requestKey): WorkflowRun
    {
        $organization = $this->tenancy->currentOrFail();
        $workflow->loadMissing('currentVersion');
        $version = $workflow->currentVersion ?? throw ValidationException::withMessages(['workflow' => 'Publish the workflow before starting it.']);

        $memberIds = array_values(array_map(intval(...), $organization->memberships()->where('status', MembershipStatus::Active)->pluck('user_id')->all()));

        $input = $this->triggers->manual()->normalizeInput(
            $version->graph()->triggerConfig(),
            $values,
            $organization->currency,
            $memberIds,
        );

        if ($input['errors'] !== []) {
            throw ValidationException::withMessages(collect($input['errors'])->mapWithKeys(fn (string $message, string $key): array => ["input.{$key}" => $message])->all());
        }

        return $this->engine->start($workflow, input: $input['values'], actor: $actor, idempotencyKey: "manual:{$actor->id}:{$requestKey}");
    }
}
