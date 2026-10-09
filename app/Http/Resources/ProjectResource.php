<?php

namespace App\Http\Resources;

use App\Models\Project;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Project
 */
class ProjectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status->toOption(),
            'priority' => $this->priority->toOption(),
            'owner' => $this->whenLoaded('owner', fn () => $this->owner ? (new UserSummaryResource($this->owner))->resolve($request) : null),
            'members' => UserSummaryResource::collection($this->whenLoaded('members')),
            'start_date' => $this->start_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'is_overdue' => $this->isOverdue(),
            'budget' => $this->budget_amount === null ? null : [
                'amount' => $this->budget_amount,
                'currency' => $this->budget_currency,
                'input' => Money::toDecimalString($this->budget_amount, (string) $this->budget_currency),
            ],
            'tags' => $this->tags ?? [],
            'tasks_count' => $this->tasks_count ?? null,
            'done_tasks_count' => $this->done_tasks_count ?? null,
            'open_issues_count' => $this->open_issues_count ?? null,
            'progress' => isset($this->resource->tasks_count) ? $this->progress() : null,
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
