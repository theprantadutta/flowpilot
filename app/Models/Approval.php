<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use App\Enums\Priority;
use App\Enums\Role;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasComments;
use Carbon\CarbonImmutable;
use Database\Factories\ApprovalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A request for someone to say yes or no: raised by a person, or by a
 * workflow step that waits for the decision.
 *
 * @property string $id
 * @property string $organization_id
 * @property int $number
 * @property string $title
 * @property string|null $description
 * @property ApprovalStatus $status
 * @property Priority $priority
 * @property int|null $requester_id
 * @property int|null $approver_id
 * @property Role|null $approver_role
 * @property int|null $amount
 * @property string|null $currency
 * @property list<array{label: string, value: string}>|null $details
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property string|null $subject_label
 * @property string|null $workflow_run_id
 * @property string|null $workflow_step_run_id
 * @property string $when_overdue
 * @property CarbonImmutable|null $due_at
 * @property CarbonImmutable|null $reminded_at
 * @property int|null $decided_by
 * @property CarbonImmutable|null $decided_at
 * @property string|null $decision_note
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User|null $requester
 * @property-read User|null $approver
 * @property-read User|null $decider
 * @property-read WorkflowRun|null $workflowRun
 */
#[Fillable([
    'number', 'title', 'description', 'status', 'priority', 'requester_id', 'approver_id', 'approver_role',
    'amount', 'currency', 'details', 'subject_type', 'subject_id', 'subject_label', 'workflow_run_id',
    'workflow_step_run_id', 'when_overdue', 'due_at', 'reminded_at', 'decided_by', 'decided_at', 'decision_note',
])]
class Approval extends Model
{
    /** @use HasFactory<ApprovalFactory> */
    use BelongsToOrganization, HasAttachments, HasComments, HasFactory, HasUuids;

    public const string WHEN_OVERDUE_REMIND = 'remind';

    public const string WHEN_OVERDUE_REJECT = 'reject';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'priority' => 'medium',
        'description' => null,
        'requester_id' => null,
        'approver_id' => null,
        'approver_role' => null,
        'amount' => null,
        'currency' => null,
        'details' => null,
        'subject_type' => null,
        'subject_id' => null,
        'subject_label' => null,
        'workflow_run_id' => null,
        'workflow_step_run_id' => null,
        'when_overdue' => self::WHEN_OVERDUE_REMIND,
        'due_at' => null,
        'reminded_at' => null,
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
            'status' => ApprovalStatus::class,
            'priority' => Priority::class,
            'approver_role' => Role::class,
            'number' => 'integer',
            'amount' => 'integer',
            'details' => 'array',
            'due_at' => 'datetime',
            'reminded_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    public function reference(): string
    {
        return 'A-'.$this->number;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /**
     * @return BelongsTo<WorkflowRun, $this>
     */
    public function workflowRun(): BelongsTo
    {
        return $this->belongsTo(WorkflowRun::class);
    }

    public function isOverdue(): bool
    {
        return $this->status->isOpen() && $this->due_at !== null && $this->due_at->isPast();
    }

    /**
     * Whether this user is one of the people the request is waiting on.
     */
    public function isWaitingOn(User $user, ?OrganizationMembership $membership): bool
    {
        if ($this->approver_id !== null) {
            return $this->approver_id === $user->id;
        }

        return $membership !== null && $this->approver_role !== null && $membership->role === $this->approver_role;
    }

    /**
     * Open approvals this person is expected to decide.
     *
     * @param  Builder<self>  $query
     */
    public function scopeWaitingOn(Builder $query, User $user, ?OrganizationMembership $membership): void
    {
        $query->where('status', ApprovalStatus::Pending->value)
            ->where(fn (Builder $query) => $query
                ->where('approver_id', $user->id)
                ->when($membership, fn (Builder $query, OrganizationMembership $membership) => $query
                    ->orWhere(fn (Builder $query) => $query->whereNull('approver_id')->where('approver_role', $membership->role->value))));
    }
}
