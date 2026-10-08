<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A record of one outgoing webhook: what was sent where, and what came back.
 * Retries reuse the same row and delivery key.
 *
 * @property string $id
 * @property string $organization_id
 * @property string|null $workflow_step_run_id
 * @property string $event
 * @property string $url
 * @property array<string, mixed> $payload
 * @property string $delivery_key
 * @property string $status
 * @property int|null $response_status
 * @property string|null $response_body
 * @property string|null $error
 * @property int $attempts
 * @property int|null $duration_ms
 * @property CarbonImmutable|null $delivered_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'workflow_step_run_id', 'event', 'url', 'payload', 'delivery_key', 'status', 'response_status',
    'response_body', 'error', 'attempts', 'duration_ms', 'delivered_at',
])]
class WebhookDelivery extends Model
{
    use BelongsToOrganization, HasUuids;

    public const string STATUS_PENDING = 'pending';

    public const string STATUS_DELIVERED = 'delivered';

    public const string STATUS_FAILED = 'failed';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'workflow_step_run_id' => null,
        'status' => self::STATUS_PENDING,
        'response_status' => null,
        'response_body' => null,
        'error' => null,
        'attempts' => 0,
        'duration_ms' => null,
        'delivered_at' => null,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'response_status' => 'integer',
            'attempts' => 'integer',
            'duration_ms' => 'integer',
            'delivered_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<WorkflowStepRun, $this>
     */
    public function stepRun(): BelongsTo
    {
        return $this->belongsTo(WorkflowStepRun::class, 'workflow_step_run_id');
    }
}
