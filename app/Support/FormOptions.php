<?php

namespace App\Support;

use App\Enums\MembershipStatus;
use App\Enums\ProjectStatus;
use App\Models\OrganizationMembership;
use App\Models\Project;
use App\Support\Tenancy\Tenancy;

/**
 * Choices shared by the project, task and issue forms: who work can be
 * assigned to, and which projects it can belong to.
 */
class FormOptions
{
    public function __construct(private readonly Tenancy $tenancy) {}

    /**
     * @return list<array{id: int, name: string, avatar: string|null, role: string}>
     */
    public function members(): array
    {
        return array_values($this->tenancy->currentOrFail()
            ->memberships()
            ->where('status', MembershipStatus::Active)
            ->with('user:id,name,avatar_path')
            ->get()
            ->sortBy(fn (OrganizationMembership $membership): string => $membership->user->name)
            ->map(fn (OrganizationMembership $membership): array => [
                'id' => $membership->user_id,
                'name' => $membership->user->name,
                'avatar' => $membership->user->avatar,
                'role' => $membership->role->label(),
            ])
            ->all());
    }

    /**
     * Projects that can still take new work.
     *
     * @return list<array{id: string, name: string}>
     */
    public function projects(): array
    {
        return array_values(Project::query()
            ->whereIn('status', array_map(fn (ProjectStatus $status): string => $status->value, ProjectStatus::open()))
            ->orderBy('name')
            ->get(['id', 'organization_id', 'name'])
            ->map(fn (Project $project): array => ['id' => $project->id, 'name' => $project->name])
            ->all());
    }
}
