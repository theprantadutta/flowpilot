<?php

namespace App\Models;

use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasComments;
use Carbon\CarbonImmutable;
use Database\Factories\IssueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $organization_id
 * @property string|null $project_id
 * @property int $number
 * @property string $title
 * @property string|null $description
 * @property IssueSeverity $severity
 * @property IssueStatus $status
 * @property int|null $reporter_id
 * @property int|null $assignee_id
 * @property CarbonImmutable|null $due_date
 * @property list<string>|null $tags
 * @property CarbonImmutable|null $resolved_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Project|null $project
 * @property-read User|null $assignee
 * @property-read User|null $reporter
 */
#[Fillable([
    'project_id', 'number', 'title', 'description', 'severity', 'status', 'reporter_id',
    'assignee_id', 'due_date', 'tags', 'resolved_at',
])]
class Issue extends Model
{
    /** @use HasFactory<IssueFactory> */
    use BelongsToOrganization, HasAttachments, HasComments, HasFactory, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'severity' => 'medium',
        'status' => 'open',
        'project_id' => null,
        'description' => null,
        'reporter_id' => null,
        'assignee_id' => null,
        'due_date' => null,
        'tags' => null,
        'resolved_at' => null,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'severity' => IssueSeverity::class,
            'status' => IssueStatus::class,
            'number' => 'integer',
            'due_date' => 'date',
            'tags' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    public function reference(): string
    {
        return 'I-'.$this->number;
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', [IssueStatus::Open->value, IssueStatus::Investigating->value]);
    }
}
