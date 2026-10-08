<?php

namespace App\Models;

use App\Enums\Priority;
use App\Enums\ProjectStatus;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasAttachments;
use Carbon\CarbonImmutable;
use Database\Factories\ProjectFactory;
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
 * @property string $name
 * @property string|null $description
 * @property ProjectStatus $status
 * @property Priority $priority
 * @property int|null $owner_id
 * @property CarbonImmutable|null $start_date
 * @property CarbonImmutable|null $due_date
 * @property int|null $budget_amount
 * @property string|null $budget_currency
 * @property list<string>|null $tags
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User|null $owner
 * @property-read int|null $tasks_count
 * @property-read int|null $done_tasks_count
 * @property-read int|null $open_issues_count
 */
#[Fillable([
    'name', 'description', 'status', 'priority', 'owner_id', 'start_date', 'due_date',
    'budget_amount', 'budget_currency', 'tags', 'completed_at',
])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use BelongsToOrganization, HasAttachments, HasFactory, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'planning',
        'priority' => 'medium',
        'description' => null,
        'owner_id' => null,
        'start_date' => null,
        'due_date' => null,
        'budget_amount' => null,
        'budget_currency' => null,
        'tags' => null,
        'completed_at' => null,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'priority' => Priority::class,
            'start_date' => 'date',
            'due_date' => 'date',
            'budget_amount' => 'integer',
            'tags' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members')->withTimestamps();
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * @return HasMany<Issue, $this>
     */
    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }

    /**
     * Load the counts the project cards and header use, in one query.
     *
     * @param  Builder<self>  $query
     */
    public function scopeWithProgress(Builder $query): void
    {
        $query->withCount([
            'tasks',
            'tasks as done_tasks_count' => fn (Builder $tasks) => $tasks->where('status', 'done'),
            'issues as open_issues_count' => fn (Builder $issues) => $issues->whereIn('status', ['open', 'investigating']),
        ]);
    }

    /**
     * Share of tasks done, 0–100. Needs scopeWithProgress().
     */
    public function progress(): int
    {
        $total = (int) ($this->tasks_count ?? 0);

        if ($total === 0) {
            return $this->status === ProjectStatus::Completed ? 100 : 0;
        }

        return (int) round(((int) ($this->done_tasks_count ?? 0)) / $total * 100);
    }

    public function isOverdue(): bool
    {
        return $this->due_date !== null
            && $this->due_date->isPast()
            && ! $this->due_date->isToday()
            && in_array($this->status, ProjectStatus::open(), true);
    }

    public function isMember(User $user): bool
    {
        return $this->owner_id === $user->id
            || $this->members()->whereKey($user->id)->exists();
    }
}
