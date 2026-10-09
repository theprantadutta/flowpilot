<?php

namespace App\Enums;

/**
 * Everything a member of an organization can be allowed to do.
 *
 * Code checks permissions, never role names, so roles can be re-cut (and custom
 * roles added) without touching feature code.
 */
enum Permission: string
{
    case DashboardView = 'dashboard.view';

    case ProjectsView = 'projects.view';
    case ProjectsCreate = 'projects.create';
    case ProjectsUpdate = 'projects.update';
    case ProjectsDelete = 'projects.delete';

    case TasksView = 'tasks.view';
    case TasksCreate = 'tasks.create';
    case TasksUpdate = 'tasks.update';
    case TasksDelete = 'tasks.delete';

    case IssuesView = 'issues.view';
    case IssuesCreate = 'issues.create';
    case IssuesUpdate = 'issues.update';
    case IssuesDelete = 'issues.delete';

    case WorkflowsView = 'workflows.view';
    case WorkflowsCreate = 'workflows.create';
    case WorkflowsPublish = 'workflows.publish';
    case WorkflowsExecute = 'workflows.execute';
    case WorkflowsDelete = 'workflows.delete';

    case ApprovalsView = 'approvals.view';
    case ApprovalsApprove = 'approvals.approve';
    case ApprovalsReject = 'approvals.reject';
    case ApprovalsOverride = 'approvals.override';
    case ApprovalsRequest = 'approvals.request';

    case InventoryView = 'inventory.view';
    case InventoryManage = 'inventory.manage';
    case InventoryRequest = 'inventory.request';

    case ReportsView = 'reports.view';
    case ReportsExport = 'reports.export';

    case MembersView = 'members.view';
    case MembersInvite = 'members.invite';
    case MembersManage = 'members.manage';

    case SettingsManage = 'settings.manage';
    case BillingManage = 'billing.manage';
    case AuditView = 'audit.view';
    case AiUse = 'ai.use';

    public function label(): string
    {
        return match ($this) {
            self::DashboardView => 'View the overview',
            self::ProjectsView => 'View projects',
            self::ProjectsCreate => 'Create projects',
            self::ProjectsUpdate => 'Edit projects',
            self::ProjectsDelete => 'Delete projects',
            self::TasksView => 'View tasks',
            self::TasksCreate => 'Create tasks',
            self::TasksUpdate => 'Edit tasks',
            self::TasksDelete => 'Delete tasks',
            self::IssuesView => 'View issues',
            self::IssuesCreate => 'Report issues',
            self::IssuesUpdate => 'Edit issues',
            self::IssuesDelete => 'Delete issues',
            self::WorkflowsView => 'View workflows and runs',
            self::WorkflowsCreate => 'Build and edit workflows',
            self::WorkflowsPublish => 'Publish workflows',
            self::WorkflowsExecute => 'Start workflow runs',
            self::WorkflowsDelete => 'Delete workflows',
            self::ApprovalsView => 'View approvals',
            self::ApprovalsApprove => 'Approve requests',
            self::ApprovalsReject => 'Reject requests',
            self::ApprovalsOverride => 'Decide any request, whoever it is waiting on',
            self::ApprovalsRequest => 'Raise approval requests',
            self::InventoryView => 'View inventory',
            self::InventoryManage => 'Manage stock, suppliers and locations',
            self::InventoryRequest => 'Raise purchase requests',
            self::ReportsView => 'View reports',
            self::ReportsExport => 'Export reports',
            self::MembersView => 'View members',
            self::MembersInvite => 'Invite members',
            self::MembersManage => 'Change roles and remove members',
            self::SettingsManage => 'Manage organization settings',
            self::BillingManage => 'Manage plan and billing',
            self::AuditView => 'View the audit log',
            self::AiUse => 'Use AI insights',
        };
    }

    /**
     * The area a permission belongs to, e.g. "projects".
     */
    public function group(): string
    {
        return explode('.', $this->value)[0];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
