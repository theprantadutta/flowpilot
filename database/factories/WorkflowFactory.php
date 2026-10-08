<?php

namespace Database\Factories;

use App\Enums\WorkflowStatus;
use App\Models\Organization;
use App\Models\Workflow;
use App\Models\WorkflowVersion;
use App\Workflows\Definition\WorkflowDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Workflow>
 */
class WorkflowFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->randomElement(['Purchase approval', 'Escalate critical issues', 'New starter onboarding', 'Supplier invoice check']),
            'description' => fake()->sentence(8),
            'status' => WorkflowStatus::Draft,
            'trigger_type' => 'manual',
            'draft_definition' => [...self::graph(), 'trigger' => 'manual'],
        ];
    }

    /**
     * A draft with the given graph and trigger.
     *
     * @param  array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>}  $graph
     */
    public function withGraph(array $graph, string $trigger = 'manual'): static
    {
        return $this->state(fn (): array => [
            'trigger_type' => $trigger,
            'draft_definition' => [...$graph, 'trigger' => $trigger],
        ]);
    }

    /**
     * Published as version 1 and switched on.
     */
    public function published(): static
    {
        return $this->state(fn (): array => ['status' => WorkflowStatus::Active, 'published_at' => now()])
            ->afterCreating(function (Workflow $workflow): void {
                $definition = WorkflowDefinition::fromArray($workflow->draft_definition);

                $version = WorkflowVersion::query()->forceCreate([
                    'organization_id' => $workflow->organization_id,
                    'workflow_id' => $workflow->id,
                    'version' => 1,
                    'trigger_type' => $workflow->draftTrigger(),
                    'definition' => $definition->toArray(),
                    'checksum' => $definition->checksum(),
                    'published_at' => now(),
                ]);

                $workflow->forceFill(['current_version_id' => $version->id])->save();
            });
    }

    /**
     * Trigger, then straight to the end.
     *
     * @return array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>}
     */
    public static function graph(): array
    {
        return [
            'nodes' => [
                ['id' => 'trigger', 'type' => 'trigger', 'position' => ['x' => 0, 'y' => 0], 'data' => ['label' => 'Start', 'config' => ['inputs' => []]]],
                ['id' => 'end', 'type' => 'end', 'position' => ['x' => 0, 'y' => 150], 'data' => ['label' => 'Done', 'config' => []]],
            ],
            'edges' => [
                ['id' => 'trigger-next-end', 'source' => 'trigger', 'target' => 'end', 'sourceHandle' => 'next'],
            ],
        ];
    }
}
