<?php

namespace App\Models;

use App\Enums\NodeType;
use App\Enums\StepRunStatus;
use App\Models\Concerns\BelongsToOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One step a run took (or is taking): which node, what it was given, what it
 * produced, which path it chose and how many attempts it needed.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $workflow_run_id
 * @property int $sequence
 * @property string $node_id
 * @property NodeType $node_type
 * @property string $label
 * @property StepRunStatus $status
 * @property string|null $outcome
 * @property array<string, mixed>|null $input
 * @property array<string, mixed>|null $output
 * @property string|null $error
 * @property int $attempts
 * @property string|null $waiting_on_type
 * @property string|null $waiting_on_id
 * @property CarbonImmutable|null $resume_at
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read WorkflowRun $run
 */
#[Fillable([
    'workflow_run_id', 'sequence', 'node_id', 'node_type', 'label', 'status', 'outcome', 'input', 'output',
    'error', 'attempts', 'waiting_on_type', 'waiting_on_id', 'resume_at', 'started_at', 'completed_at',
])]
class WorkflowStepRun extends Model
{
    use BelongsToOrganization, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'outcome' => null,
        'input' => null,
        'output' => null,
        'error' => null,
        'attempts' => 0,
        'waiting_on_type' => null,
        'waiting_on_id' => null,
        'resume_at' => null,
        'started_at' => null,
        'completed_at' => null,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'node_type' => NodeType::class,
            'status' => StepRunStatus::class,
            'sequence' => 'integer',
            'attempts' => 'integer',
            'input' => 'array',
            'output' => 'array',
            'resume_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<WorkflowRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(WorkflowRun::class, 'workflow_run_id');
    }
}
