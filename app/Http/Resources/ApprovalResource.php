<?php

namespace App\Http\Resources;

use App\Models\Approval;
use App\Support\Money;
use App\Support\Tenancy\Tenancy;
use App\Workflows\Support\RecordLinks;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Approval
 */
class ApprovalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $organization = app(Tenancy::class)->current();

        return [
            'id' => $this->id,
            'reference' => $this->reference(),
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status->toOption(),
            'is_open' => $this->status->isOpen(),
            'priority' => $this->priority->toOption(),
            'requester' => $this->whenLoaded('requester', fn () => $this->requester ? (new UserSummaryResource($this->requester))->resolve($request) : null),
            'approver' => $this->whenLoaded('approver', fn () => $this->approver ? (new UserSummaryResource($this->approver))->resolve($request) : null),
            'approver_role' => $this->approver_role ? ['value' => $this->approver_role->value, 'label' => $this->approver_role->label()] : null,
            'amount' => $this->amount !== null && $this->currency !== null ? [
                'minor' => $this->amount,
                'currency' => $this->currency,
                'formatted' => Money::format($this->amount, $this->currency, $organization->locale ?? 'en'),
                'input' => Money::toDecimalString($this->amount, $this->currency),
            ] : null,
            'details' => $this->details ?? [],
            'subject' => $this->subject_type && $this->subject_id ? [
                'type' => $this->subject_type,
                'label' => $this->subject_label,
                'url' => $organization ? RecordLinks::forType($this->subject_type, $this->subject_id, $organization) : null,
            ] : null,
            'workflow_run_url' => $this->workflow_run_id && $organization ? RecordLinks::forType('workflow_run', $this->workflow_run_id, $organization) : null,
            'when_overdue' => $this->when_overdue,
            'due_at' => $this->due_at?->toIso8601String(),
            'is_overdue' => $this->isOverdue(),
            'decider' => $this->whenLoaded('decider', fn () => $this->decider ? (new UserSummaryResource($this->decider))->resolve($request) : null),
            'decided_at' => $this->decided_at?->toIso8601String(),
            'decision_note' => $this->decision_note,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
