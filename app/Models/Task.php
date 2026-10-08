<?php

namespace App\Models;

use App\Enums\Priority;
use App\Enums\TaskStatus;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasComments;
use Carbon\CarbonImmutable;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $organization_id
 * @property string|null $project_id
 * @property int $number
 * @property string $title
 * @property string|null $description
 * @property TaskStatus $status
 * @property Priority $priority
 * @property int|null $assignee_id
 * @property int|null $reporter_id
 * @property CarbonImmutable|null $due_date
 * @property float $position
 * @property list<string>|null $tags
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $overdue_notified_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Project|null $project
 * @property-read User|null $assignee
 * @property-read User|null $reporter
 */
#[Fillable([
    'project_id', 'number', 'title', 'description', 'status', 'priority', 'assignee_id',
    'reporter_id', 'due_date', 'position', 'tags', 'completed_at', 'overdue_notified_at',
])]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use BelongsToOrganization, HasAttachments, HasComments, HasFactory, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'todo',
        'priority' => 'medium',
        'project_id' => null,
        'description' => null,
        'assignee_id' => null,
        'reporter_id' => null,
        'due_date' => null,
        'position' => 0,
        'tags' => null,
        'completed_at' => null,
        'overdue_notified_at' => null,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'priority' => Priority::class,
            'number' => 'integer',
            'due_date' => 'date',
            'position' => 'float',
            'tags' => 'array',
            'completed_at' => 'datetime',
            'overdue_notified_at' => 'datetime',
        ];
    }

    public function reference(): string
    {
        return 'T-'.$this->number;
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
     * @return HasMany<TaskChecklistItem, $this>
     */
    public function checklistItems(): HasMany
    {
        return $this->hasMany(TaskChecklistItem::class)->orderBy('position');
    }

    /**
     * Tasks that must be done before this one can be.
     *
     * @return BelongsToMany<Task, $this>
     */
    public function dependencies(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'task_dependencies', 'task_id', 'depends_on_id')->withTimestamps();
    }

    /**
     * Tasks that are waiting on this one.
     *
     * @return BelongsToMany<Task, $this>
     */
    public function dependents(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'task_dependencies', 'depends_on_id', 'task_id')->withTimestamps();
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', '!=', TaskStatus::Done->value);
    }

    /**
     * Open tasks whose due date has passed in the organization's timezone.
     *
     * @param  Builder<self>  $query
     */
    public function scopeOverdue(Builder $query, ?string $today = null): void
    {
        $query->open()->whereNotNull('due_date')->whereDate('due_date', '<', $today ?? now()->toDateString());
    }

    public function isOverdue(?string $today = null): bool
    {
        return ! $this->status->isDone()
            && $this->due_date !== null
            && $this->due_date->toDateString() < ($today ?? now()->toDateString());
    }
}
