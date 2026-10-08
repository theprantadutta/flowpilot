<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Workflows\Definition\WorkflowDefinition;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * A published snapshot of a workflow. Never changed after it is written, so
 * every run can be explained by exactly the graph it executed.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $workflow_id
 * @property int $version
 * @property string $trigger_type
 * @property array<string, mixed> $definition
 * @property string $checksum
 * @property string|null $notes
 * @property int|null $published_by
 * @property CarbonImmutable $published_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Workflow $workflow
 * @property-read User|null $publisher
 */
#[Fillable(['workflow_id', 'version', 'trigger_type', 'definition', 'checksum', 'notes', 'published_by', 'published_at'])]
class WorkflowVersion extends Model
{
    use BelongsToOrganization, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'notes' => null,
        'published_by' => null,
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Published workflow versions cannot be changed.');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'definition' => 'array',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Workflow, $this>
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function graph(): WorkflowDefinition
    {
        return WorkflowDefinition::fromArray($this->definition);
    }
}
