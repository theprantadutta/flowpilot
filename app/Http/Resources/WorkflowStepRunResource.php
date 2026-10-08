<?php

namespace App\Http\Resources;

use App\Models\WorkflowStepRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WorkflowStepRun
 */
class WorkflowStepRunResource extends JsonResource
{
    /**
     * @param  array<string, string>  $outcomeLabels  Path labels for the step's node, e.g. ["true" => "Yes"].
     */
    public function __construct(WorkflowStepRun $resource, private readonly array $outcomeLabels = [])
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sequence' => $this->sequence,
            'node_id' => $this->node_id,
            'type' => [
                'value' => $this->node_type->value,
                'label' => $this->node_type->label(),
                'icon' => $this->node_type->icon(),
                'tone' => $this->node_type->tone(),
            ],
            'label' => $this->label,
            'status' => $this->status->toOption(),
            'outcome' => $this->outcome,
            'outcome_label' => $this->outcome ? ($this->outcomeLabels[$this->outcome] ?? null) : null,
            'input' => $this->input,
            'output' => $this->output,
            'error' => $this->error,
            'attempts' => $this->attempts,
            'resume_at' => $this->resume_at?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
        ];
    }
}
