<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;

class ProjectUpdatedNotification extends TenantNotification
{
    public string $projectId;

    public string $projectName;

    public function __construct(
        Project $project,
        public string $fromStatus,
        public string $toStatus,
        public ?string $changedBy = null,
    ) {
        parent::__construct();

        $this->projectId = $project->id;
        $this->projectName = $project->name;
    }

    public static function statusChanged(Project $project, ProjectStatus $from, ?User $actor): self
    {
        return new self($project, $from->label(), $project->status->label(), $actor?->name);
    }

    public function type(): NotificationType
    {
        return NotificationType::ProjectUpdated;
    }

    public function title(): string
    {
        return "{$this->projectName} is now {$this->toStatus}";
    }

    public function body(): ?string
    {
        return ($this->changedBy ?? 'Someone')." moved it from {$this->fromStatus} to {$this->toStatus}.";
    }

    public function url(): ?string
    {
        return $this->tenantRoute('projects.show', ['project' => $this->projectId]);
    }

    public function tone(): string
    {
        return $this->toStatus === ProjectStatus::Completed->label() ? 'success' : 'info';
    }

    public function actorName(): ?string
    {
        return $this->changedBy;
    }

    public function actionText(): string
    {
        return 'Open project';
    }
}
