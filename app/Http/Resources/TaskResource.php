<?php

namespace App\Http\Resources;

use App\Models\Task;
use App\Models\TaskChecklistItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Task
 */
class TaskResource extends JsonResource
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
            'status' => $this->status->toOption(),
            'priority' => $this->priority->toOption(),
            'project' => $this->whenLoaded('project', fn () => $this->project ? ['id' => $this->project->id, 'name' => $this->project->name] : null),
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee ? (new UserSummaryResource($this->assignee))->resolve($request) : null),
            'reporter' => $this->whenLoaded('reporter', fn () => $this->reporter ? (new UserSummaryResource($this->reporter))->resolve($request) : null),
            'due_date' => $this->due_date?->toDateString(),
            'is_overdue' => $this->isOverdue(),
            'position' => $this->position,
            'tags' => $this->tags ?? [],
            'checklist' => $this->whenLoaded('checklistItems', fn () => [
                'done' => $this->checklistItems->where('is_done', true)->count(),
                'total' => $this->checklistItems->count(),
                'items' => $this->checklistItems->map(fn (TaskChecklistItem $item): array => [
                    'id' => $item->id,
                    'body' => $item->body,
                    'is_done' => $item->is_done,
                ])->values(),
            ]),
            'checklist_summary' => $this->when(
                isset($this->resource->checklist_items_count),
                fn () => ['done' => (int) $this->resource->done_checklist_items_count, 'total' => (int) $this->resource->checklist_items_count],
            ),
            'comments_count' => $this->when(isset($this->resource->comments_count), fn () => (int) $this->resource->comments_count),
            'attachments_count' => $this->when(isset($this->resource->attachments_count), fn () => (int) $this->resource->attachments_count),
            'dependencies' => $this->whenLoaded('dependencies', fn () => $this->dependencies->map(fn (Task $blocker): array => [
                'id' => $blocker->id,
                'reference' => $blocker->reference(),
                'title' => $blocker->title,
                'status' => $blocker->status->toOption(),
            ])->values()),
            'dependents' => $this->whenLoaded('dependents', fn () => $this->dependents->map(fn (Task $waiting): array => [
                'id' => $waiting->id,
                'reference' => $waiting->reference(),
                'title' => $waiting->title,
                'status' => $waiting->status->toOption(),
            ])->values()),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'can' => $user ? [
                'update' => $user->can('update', $this->resource),
                'delete' => $user->can('delete', $this->resource),
            ] : null,
        ];
    }
}
