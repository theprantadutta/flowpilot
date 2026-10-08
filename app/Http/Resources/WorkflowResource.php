<?php

namespace App\Http\Resources;

use App\Models\Workflow;
use App\Workflows\Triggers\TriggerRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Workflow
 */
class WorkflowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $trigger = app(TriggerRegistry::class)->find($this->trigger_type);
        $attributes = $this->getAttributes();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status->toOption(),
            'trigger' => ['value' => $this->trigger_type, 'label' => $trigger?->label() ?? $this->trigger_type],
            'template' => $this->template,
            'version' => $this->whenLoaded('currentVersion', fn () => $this->currentVersion?->version),
            'has_unpublished_changes' => $this->whenLoaded('currentVersion', fn () => $this->hasUnpublishedChanges()),
            'published_at' => $this->published_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'editor' => $this->whenLoaded('editor', fn () => $this->editor ? new UserSummaryResource($this->editor) : null),
            'runs_count' => isset($attributes['runs_count']) ? (int) $attributes['runs_count'] : null,
            'failed_runs_count' => isset($attributes['failed_runs_count']) ? (int) $attributes['failed_runs_count'] : null,
            'active_runs_count' => isset($attributes['active_runs_count']) ? (int) $attributes['active_runs_count'] : null,
            'last_run_at' => isset($attributes['last_run_at']) && is_string($attributes['last_run_at']) ? CarbonImmutable::parse($attributes['last_run_at'])->toIso8601String() : null,
        ];
    }
}
