<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An append-only record of something that happened: who did what to which
 * record, and what changed. Doubles as the activity feed and the audit trail.
 *
 * @property string $id
 * @property string|null $organization_id
 * @property int|null $actor_id
 * @property string $actor_type
 * @property string $action
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property string|null $subject_label
 * @property string|null $context_type
 * @property string|null $context_id
 * @property array<string, mixed>|null $properties
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon $created_at
 * @property-read User|null $actor
 */
#[Fillable([
    'organization_id', 'actor_id', 'actor_type', 'action', 'subject_type', 'subject_id', 'subject_label',
    'context_type', 'context_id', 'properties', 'ip_address', 'user_agent', 'created_at',
])]
class ActivityLog extends Model
{
    use BelongsToOrganization, HasUuids;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Events recorded against a record, or with that record as their context.
     *
     * @param  Builder<self>  $query
     */
    public function scopeAbout(Builder $query, Model $record): void
    {
        $type = $record->getMorphClass();
        $id = (string) $record->getKey();

        $query->where(function (Builder $query) use ($type, $id): void {
            $query->where(fn (Builder $q) => $q->where('subject_type', $type)->where('subject_id', $id))
                ->orWhere(fn (Builder $q) => $q->where('context_type', $type)->where('context_id', $id));
        });
    }
}
