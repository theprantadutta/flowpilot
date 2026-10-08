<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A step inside a task. Always reached through its task, which carries the
 * tenant scope.
 *
 * @property string $id
 * @property string $task_id
 * @property string $body
 * @property bool $is_done
 * @property int $position
 * @property int|null $completed_by
 * @property CarbonImmutable|null $completed_at
 */
#[Fillable(['body', 'is_done', 'position', 'completed_by', 'completed_at'])]
class TaskChecklistItem extends Model
{
    use HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_done' => false,
        'position' => 0,
        'completed_by' => null,
        'completed_at' => null,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_done' => 'boolean',
            'position' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
