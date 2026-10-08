<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $commentable_type
 * @property string $commentable_id
 * @property int|null $author_id
 * @property string $body
 * @property CarbonImmutable|null $edited_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User|null $author
 */
#[Fillable(['author_id', 'body', 'edited_at'])]
class Comment extends Model
{
    use BelongsToOrganization, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'edited_at' => null,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['edited_at' => 'datetime'];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return array{id: string, body: string, author: array{id: int|null, name: string, avatar: string|null}, created_at: string|null, edited: bool, can_delete: bool}
     */
    public function toCommentArray(?User $viewer): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'author' => [
                'id' => $this->author_id,
                'name' => $this->author->name ?? 'A former member',
                'avatar' => $this->author?->avatar,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
            'edited' => $this->edited_at !== null,
            'can_delete' => $viewer !== null && $viewer->id === $this->author_id,
        ];
    }
}
