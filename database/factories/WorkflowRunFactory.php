<?php

namespace Database\Factories;

use App\Enums\WorkflowRunStatus;
use App\Models\Workflow;
use App\Models\WorkflowRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowRun>
 */
class WorkflowRunFactory extends Factory
{
    private static int $number = 0;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workflow_id' => Workflow::factory()->published(),
            'organization_id' => fn (array $attributes): string => self::workflow($attributes)->organization_id,
            'workflow_version_id' => fn (array $attributes): ?string => self::workflow($attributes)->current_version_id,
            'number' => ++self::$number,
            'status' => WorkflowRunStatus::Completed,
            'trigger_type' => 'manual',
            'context' => ['workflow' => ['name' => 'Purchase approval', 'version' => 1], 'steps' => [], 'meta' => ['depth' => 0]],
            'started_at' => now()->subMinutes(5),
            'completed_at' => now()->subMinutes(4),
        ];
    }

    public function status(WorkflowRunStatus $status): static
    {
        return $this->state(fn (): array => [
            'status' => $status,
            'completed_at' => $status === WorkflowRunStatus::Completed ? now() : null,
            'failed_at' => $status === WorkflowRunStatus::Failed ? now() : null,
            'error' => $status === WorkflowRunStatus::Failed ? 'The receiving system answered 500.' : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private static function workflow(array $attributes): Workflow
    {
        return Workflow::withoutOrganizationScope()->whereKey((string) $attributes['workflow_id'])->firstOrFail();
    }
}
