<?php

namespace App\Models;

use App\Enums\AiBriefStatus;
use App\Models\Concerns\BelongsToOrganization;
use Carbon\CarbonImmutable;
use Database\Factories\AiBriefFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An operations brief written for one member, from the facts they are allowed
 * to see. Also the record of each AI call: model, tokens and time taken.
 *
 * @property string $id
 * @property string $organization_id
 * @property int $user_id
 * @property AiBriefStatus $status
 * @property array{headline: string, items: list<array{title: string, detail: string, severity: string, facts: list<string>}>, actions: list<array{label: string, fact: string}>}|null $content
 * @property array<string, array{label: string, url: string|null, area: string}>|null $facts
 * @property bool $used_fallback
 * @property string|null $provider
 * @property string|null $model
 * @property int|null $prompt_tokens
 * @property int|null $completion_tokens
 * @property int|null $duration_ms
 * @property string|null $error
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 */
#[Fillable(['user_id', 'status', 'content', 'facts', 'used_fallback', 'provider', 'model', 'prompt_tokens', 'completion_tokens', 'duration_ms', 'error', 'completed_at'])]
class AiBrief extends Model
{
    /** @use HasFactory<AiBriefFactory> */
    use BelongsToOrganization, HasFactory, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'content' => null,
        'facts' => null,
        'used_fallback' => false,
        'provider' => null,
        'model' => null,
        'prompt_tokens' => null,
        'completion_tokens' => null,
        'duration_ms' => null,
        'error' => null,
        'completed_at' => null,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AiBriefStatus::class,
            'content' => 'array',
            'facts' => 'array',
            'used_fallback' => 'boolean',
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'duration_ms' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
