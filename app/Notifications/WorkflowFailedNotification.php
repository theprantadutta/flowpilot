<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\WorkflowRun;

class WorkflowFailedNotification extends TenantNotification
{
    public string $runId;

    public string $reference;

    public string $workflowName;

    public ?string $subjectLabel;

    public function __construct(WorkflowRun $run, public ?string $stepLabel, public string $error)
    {
        parent::__construct();

        $this->runId = $run->id;
        $this->reference = $run->reference();
        $this->workflowName = (string) data_get($run->context, 'workflow.name', 'A workflow');
        $this->subjectLabel = $run->subject_label;
    }

    public function type(): NotificationType
    {
        return NotificationType::WorkflowFailed;
    }

    public function title(): string
    {
        return "{$this->workflowName} failed ({$this->reference})";
    }

    public function body(): ?string
    {
        $where = $this->stepLabel ? "At \"{$this->stepLabel}\": " : '';
        $about = $this->subjectLabel ? " The run was about {$this->subjectLabel}." : '';

        return "{$where}{$this->error}{$about}";
    }

    public function url(): ?string
    {
        return $this->tenantRoute('workflow-runs.show', ['run' => $this->runId]);
    }

    public function tone(): string
    {
        return 'danger';
    }

    public function actionText(): string
    {
        return 'Open run';
    }
}
