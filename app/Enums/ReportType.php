<?php

namespace App\Enums;

/**
 * The reports FlowPilot offers. Each one also needs the permission for the
 * area it reads from, on top of reports.view.
 */
enum ReportType: string
{
    case ProjectProgress = 'project-progress';
    case TaskCompletion = 'task-completion';
    case ApprovalTurnaround = 'approval-turnaround';
    case WorkflowExecution = 'workflow-execution';
    case WorkflowFailures = 'workflow-failures';
    case InventoryStatus = 'inventory-status';
    case Activity = 'activity';
    case OrganizationUsage = 'organization-usage';

    public function label(): string
    {
        return match ($this) {
            self::ProjectProgress => 'Project progress',
            self::TaskCompletion => 'Task completion',
            self::ApprovalTurnaround => 'Approval turnaround',
            self::WorkflowExecution => 'Workflow execution',
            self::WorkflowFailures => 'Workflow failures',
            self::InventoryStatus => 'Inventory status',
            self::Activity => 'Activity',
            self::OrganizationUsage => 'Organization usage',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ProjectProgress => 'How far each project has got, what is late and what is blocking it.',
            self::TaskCompletion => 'Tasks created and finished over time, how many land on time, and how long they take.',
            self::ApprovalTurnaround => 'How quickly requests are decided, and how many are approved.',
            self::WorkflowExecution => 'How often each workflow runs and how many runs finish.',
            self::WorkflowFailures => 'Runs that failed, the step they stopped at and why.',
            self::InventoryStatus => 'Stock on hand, what is running low, its value and how it moved.',
            self::Activity => 'Who changed what, by area of the product.',
            self::OrganizationUsage => 'Members, records, automation and storage the organization uses.',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::ProjectProgress => 'folder-kanban',
            self::TaskCompletion => 'list-checks',
            self::ApprovalTurnaround => 'stamp',
            self::WorkflowExecution => 'git-branch',
            self::WorkflowFailures => 'circle-alert',
            self::InventoryStatus => 'boxes',
            self::Activity => 'activity',
            self::OrganizationUsage => 'gauge',
        };
    }

    /**
     * The section of the reports page the report is listed under.
     */
    public function group(): string
    {
        return match ($this) {
            self::ProjectProgress, self::TaskCompletion => 'Work',
            self::ApprovalTurnaround, self::WorkflowExecution, self::WorkflowFailures => 'Automation',
            self::InventoryStatus => 'Inventory',
            self::Activity, self::OrganizationUsage => 'Organization',
        };
    }

    /**
     * What a member needs, besides reports.view, to see the data inside.
     */
    public function permission(): Permission
    {
        return match ($this) {
            self::ProjectProgress => Permission::ProjectsView,
            self::TaskCompletion => Permission::TasksView,
            self::ApprovalTurnaround => Permission::ApprovalsView,
            self::WorkflowExecution, self::WorkflowFailures => Permission::WorkflowsView,
            self::InventoryStatus => Permission::InventoryView,
            self::Activity => Permission::DashboardView,
            self::OrganizationUsage => Permission::MembersView,
        };
    }
}
