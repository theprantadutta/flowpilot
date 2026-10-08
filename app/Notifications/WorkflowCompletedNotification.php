<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\WorkflowRun;

class WorkflowCompletedNotification extends TenantNotification
{
    public string $runId;

    public string $reference;

    public string $workflowName;

    public int $steps;

    public function __construct(WorkflowRun $run, int $steps)
    {
        parent::__construct();

        $this->runId = $run->id;
        $this->reference = $run->reference();
        $this->workflowName = (string) data_get($run->context, 'workflow.name', 'A workflow');
        $this->steps = $steps;
    }

    public function type(): NotificationType
    {
        return NotificationType::WorkflowCompleted;
    }

    public function title(): string
    {
        return "{$this->workflowName} finished ({$this->reference})";
    }

    public function body(): ?string
    {
        return $this->steps === 1 ? 'The run you started completed its step.' : "The run you started completed all {$this->steps} steps.";
    }

    public function url(): ?string
    {
        return $this->tenantRoute('workflow-runs.show', ['run' => $this->runId]);
    }

    public function tone(): string
    {
        return 'success';
    }

    public function actionText(): string
    {
        return 'Open run';
    }
}
