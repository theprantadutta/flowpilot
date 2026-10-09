<?php

namespace App\Http\Resources;

use App\Models\WorkflowRun;
use App\Support\Tenancy\Tenancy;
use App\Workflows\Support\RecordLinks;
use App\Workflows\Triggers\TriggerRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WorkflowRun
 */
class WorkflowRunResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $trigger = app(TriggerRegistry::class)->find($this->trigger_type);

        return [
            'id' => $this->id,
            'reference' => $this->reference(),
            'status' => $this->status->toOption(),
            'is_finished' => $this->status->isFinished(),
            'workflow' => $this->whenLoaded('workflow', fn () => ['id' => $this->workflow->id, 'name' => $this->workflow->name]),
            'version' => $this->whenLoaded('version', fn () => $this->version->version),
            'trigger' => ['value' => $this->trigger_type, 'label' => $trigger?->label() ?? $this->trigger_type],
            'subject' => $this->subject_type ? [
                'type' => $this->subject_type,
                'label' => $this->subject_label,
                'url' => $this->subjectUrl(),
            ] : null,
            'starter' => $this->whenLoaded('starter', fn () => $this->starter ? (new UserSummaryResource($this->starter))->resolve($request) : null),
            'error' => $this->error,
            'created_at' => $this->created_at?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'failed_at' => $this->failed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'duration_seconds' => $this->durationInSeconds(),
        ];
    }

    /**
     * A link to the record, built from its type and id without loading it.
     */
    private function subjectUrl(): ?string
    {
        $organization = app(Tenancy::class)->current();

        if ($organization === null || $this->subject_type === null || $this->subject_id === null) {
            return null;
        }

        return RecordLinks::forType($this->subject_type, $this->subject_id, $organization);
    }
}
