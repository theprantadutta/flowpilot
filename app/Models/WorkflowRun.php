<?php

namespace App\Models;

use App\Enums\WorkflowRunStatus;
use App\Models\Concerns\BelongsToOrganization;
use Carbon\CarbonImmutable;
use Database\Factories\WorkflowRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $workflow_id
 * @property string $workflow_version_id
 * @property int $number
 * @property WorkflowRunStatus $status
 * @property string $trigger_type
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property string|null $subject_label
 * @property array<string, mixed>|null $input
 * @property array<string, mixed>|null $context
 * @property string|null $current_node_id
 * @property int|null $started_by
 * @property string|null $idempotency_key
 * @property string|null $error
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $failed_at
 * @property CarbonImmutable|null $cancelled_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Workflow $workflow
 * @property-read WorkflowVersion $version
 * @property-read User|null $starter
 * @property-read Model|null $subject
 */
#[Fillable([
    'workflow_id', 'workflow_version_id', 'number', 'status', 'trigger_type', 'subject_type', 'subject_id',
    'subject_label', 'input', 'context', 'current_node_id', 'started_by', 'idempotency_key', 'error',
    'started_at', 'completed_at', 'failed_at', 'cancelled_at',
])]
class WorkflowRun extends Model
{
    /** @use HasFactory<WorkflowRunFactory> */
    use BelongsToOrganization, HasFactory, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'subject_type' => null,
        'subject_id' => null,
        'subject_label' => null,
        'input' => null,
        'context' => null,
        'current_node_id' => null,
        'started_by' => null,
        'idempotency_key' => null,
        'error' => null,
        'started_at' => null,
        'completed_at' => null,
        'failed_at' => null,
        'cancelled_at' => null,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => WorkflowRunStatus::class,
            'number' => 'integer',
            'input' => 'array',
            'context' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function reference(): string
    {
        return 'R-'.$this->number;
    }

    /**
     * @return BelongsTo<Workflow, $this>
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    /**
     * @return BelongsTo<WorkflowVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class, 'workflow_version_id');
    }

    /**
     * @return HasMany<WorkflowStepRun, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(WorkflowStepRun::class)->orderBy('sequence');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function durationInSeconds(): ?int
    {
        $end = $this->completed_at ?? $this->failed_at ?? $this->cancelled_at;

        return $this->started_at && $end ? (int) $this->started_at->diffInSeconds($end) : null;
    }
}
