<?php

namespace App\Http\Resources;

use App\Models\Issue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Issue
 */
class IssueResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'reference' => $this->reference(),
            'title' => $this->title,
            'description' => $this->description,
            'severity' => $this->severity->toOption(),
            'status' => $this->status->toOption(),
            'project' => $this->whenLoaded('project', fn () => $this->project ? ['id' => $this->project->id, 'name' => $this->project->name] : null),
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee ? new UserSummaryResource($this->assignee) : null),
            'reporter' => $this->whenLoaded('reporter', fn () => $this->reporter ? new UserSummaryResource($this->reporter) : null),
            'due_date' => $this->due_date?->toDateString(),
            'tags' => $this->tags ?? [],
            'comments_count' => $this->when(isset($this->resource->comments_count), fn () => (int) $this->resource->comments_count),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'can' => $user ? [
                'update' => $user->can('update', $this->resource),
                'delete' => $user->can('delete', $this->resource),
            ] : null,
        ];
    }
}
