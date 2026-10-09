<?php

namespace App\Models;

use App\Enums\Plan;
use App\Models\Concerns\BelongsToOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An owner asking to move to a paid plan. Until a payment provider is
 * connected, the FlowPilot team settles it from platform administration.
 *
 * @property string $id
 * @property string $organization_id
 * @property int|null $requested_by
 * @property Plan $from_plan
 * @property Plan $to_plan
 * @property string|null $message
 * @property string $status pending, approved, declined or withdrawn
 * @property int|null $decided_by
 * @property CarbonImmutable|null $decided_at
 * @property string|null $decision_note
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User|null $requester
 * @property-read Organization $organization
 */
#[Fillable(['requested_by', 'from_plan', 'to_plan', 'message', 'status', 'decided_by', 'decided_at', 'decision_note'])]
class PlanChangeRequest extends Model
{
    use BelongsToOrganization, HasUuids;

    public const string PENDING = 'pending';

    public const string APPROVED = 'approved';

    public const string DECLINED = 'declined';

    public const string WITHDRAWN = 'withdrawn';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::PENDING,
        'message' => null,
        'decided_by' => null,
        'decided_at' => null,
        'decision_note' => null,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_plan' => Plan::class,
            'to_plan' => Plan::class,
            'decided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
