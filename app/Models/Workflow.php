<?php

namespace App\Models;

use App\Enums\WorkflowStatus;
use App\Models\Concerns\BelongsToOrganization;
use App\Workflows\Definition\WorkflowDefinition;
use Carbon\CarbonImmutable;
use Database\Factories\WorkflowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $name
 * @property string|null $description
 * @property WorkflowStatus $status
 * @property string $trigger_type
 * @property array<string, mixed> $draft_definition
 * @property string|null $current_version_id
 * @property string|null $template
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read WorkflowVersion|null $currentVersion
 * @property-read User|null $creator
 * @property-read User|null $editor
 */
#[Fillable([
    'name', 'description', 'status', 'trigger_type', 'draft_definition', 'current_version_id',
    'template', 'created_by', 'updated_by', 'published_at',
])]
class Workflow extends Model
{
    /** @use HasFactory<WorkflowFactory> */
    use BelongsToOrganization, HasFactory, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'description' => null,
        'current_version_id' => null,
        'template' => null,
        'created_by' => null,
        'updated_by' => null,
        'published_at' => null,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => WorkflowStatus::class,
            'draft_definition' => 'array',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<WorkflowVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(WorkflowVersion::class)->orderByDesc('version');
    }

    /**
     * @return BelongsTo<WorkflowVersion, $this>
     */
    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class, 'current_version_id');
    }

    /**
     * @return HasMany<WorkflowRun, $this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(WorkflowRun::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function draft(): WorkflowDefinition
    {
        return WorkflowDefinition::fromArray($this->draft_definition);
    }

    /**
     * The trigger the draft uses. Until it is published, the live trigger
     * (trigger_type) stays what the current version listens for.
     */
    public function draftTrigger(): string
    {
        $trigger = $this->draft_definition['trigger'] ?? null;

        return is_string($trigger) && $trigger !== '' ? $trigger : $this->trigger_type;
    }

    public function isActive(): bool
    {
        return $this->status === WorkflowStatus::Active && $this->current_version_id !== null;
    }

    /**
     * Whether the draft differs from what was last published.
     */
    public function hasUnpublishedChanges(): bool
    {
        if ($this->current_version_id === null || $this->currentVersion === null) {
            return true;
        }

        return $this->draft()->checksum() !== $this->currentVersion->checksum
            || $this->draftTrigger() !== $this->currentVersion->trigger_type;
    }
}
