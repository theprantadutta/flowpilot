<?php

namespace App\Enums;

/**
 * The built-in organization roles. Each one is only a named set of permissions.
 *
 * Platform administration is not a role here: it is a flag on the user and has
 * its own area of the application.
 */
enum Role: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Manager = 'manager';
    case Finance = 'finance';
    case Procurement = 'procurement';
    case Operations = 'operations';
    case Employee = 'employee';
    case Auditor = 'auditor';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Admin => 'Admin',
            self::Manager => 'Manager',
            self::Finance => 'Finance',
            self::Procurement => 'Procurement',
            self::Operations => 'Operations',
            self::Employee => 'Employee',
            self::Auditor => 'Auditor',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Owner => 'Full control, including billing and ownership.',
            self::Admin => 'Manages members, settings, workflows and all work.',
            self::Manager => 'Runs projects and approves requests for their team.',
            self::Finance => 'Reviews spend and gives financial approval.',
            self::Procurement => 'Handles purchasing, suppliers and stock.',
            self::Operations => 'Keeps day-to-day work and inventory moving.',
            self::Employee => 'Works on tasks and raises requests.',
            self::Auditor => 'Read-only access, including the audit log.',
        };
    }

    /**
     * Higher numbers outrank lower ones when deciding who may manage whom.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Owner => 100,
            self::Admin => 80,
            self::Manager => 60,
            self::Finance, self::Procurement, self::Operations => 40,
            self::Employee => 20,
            self::Auditor => 10,
        };
    }

    /**
     * Whether a member holding this role may give someone the target role.
     * Ownership is never handed out by assignment.
     */
    public function canAssign(self $target): bool
    {
        if ($target === self::Owner) {
            return false;
        }

        return $this === self::Owner || $this->rank() > $target->rank();
    }

    /**
     * Whether a member holding this role may change or remove a member holding the other role.
     */
    public function outranks(self $other): bool
    {
        return $this->rank() > $other->rank();
    }

    /**
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Owner => Permission::cases(),

            self::Admin => array_values(array_filter(
                Permission::cases(),
                fn (Permission $permission): bool => $permission !== Permission::BillingManage,
            )),

            self::Manager => [
                Permission::DashboardView,
                Permission::ProjectsView, Permission::ProjectsCreate, Permission::ProjectsUpdate, Permission::ProjectsDelete,
                Permission::TasksView, Permission::TasksCreate, Permission::TasksUpdate, Permission::TasksDelete,
                Permission::IssuesView, Permission::IssuesCreate, Permission::IssuesUpdate, Permission::IssuesDelete,
                Permission::WorkflowsView, Permission::WorkflowsExecute,
                Permission::ApprovalsView, Permission::ApprovalsApprove, Permission::ApprovalsReject, Permission::ApprovalsRequest,
                Permission::InventoryView, Permission::InventoryRequest,
                Permission::ReportsView, Permission::ReportsExport,
                Permission::MembersView,
                Permission::AiUse,
            ],

            self::Finance => [
                Permission::DashboardView,
                Permission::ProjectsView,
                Permission::TasksView, Permission::TasksCreate, Permission::TasksUpdate,
                Permission::IssuesView, Permission::IssuesCreate,
                Permission::WorkflowsView, Permission::WorkflowsExecute,
                Permission::ApprovalsView, Permission::ApprovalsApprove, Permission::ApprovalsReject, Permission::ApprovalsRequest,
                Permission::InventoryView, Permission::InventoryRequest,
                Permission::ReportsView, Permission::ReportsExport,
                Permission::MembersView,
                Permission::AiUse,
            ],

            self::Procurement => [
                Permission::DashboardView,
                Permission::ProjectsView,
                Permission::TasksView, Permission::TasksCreate, Permission::TasksUpdate,
                Permission::IssuesView, Permission::IssuesCreate,
                Permission::WorkflowsView, Permission::WorkflowsExecute,
                Permission::ApprovalsView, Permission::ApprovalsApprove, Permission::ApprovalsReject, Permission::ApprovalsRequest,
                Permission::InventoryView, Permission::InventoryManage, Permission::InventoryRequest,
                Permission::ReportsView,
                Permission::MembersView,
            ],

            self::Operations => [
                Permission::DashboardView,
                Permission::ProjectsView, Permission::ProjectsUpdate,
                Permission::TasksView, Permission::TasksCreate, Permission::TasksUpdate,
                Permission::IssuesView, Permission::IssuesCreate, Permission::IssuesUpdate,
                Permission::WorkflowsView, Permission::WorkflowsExecute,
                Permission::ApprovalsView, Permission::ApprovalsRequest,
                Permission::InventoryView, Permission::InventoryManage, Permission::InventoryRequest,
                Permission::ReportsView,
                Permission::MembersView,
            ],

            self::Employee => [
                Permission::DashboardView,
                Permission::ProjectsView,
                Permission::TasksView, Permission::TasksCreate, Permission::TasksUpdate,
                Permission::IssuesView, Permission::IssuesCreate,
                Permission::WorkflowsView, Permission::WorkflowsExecute,
                Permission::ApprovalsView, Permission::ApprovalsRequest,
                Permission::InventoryView, Permission::InventoryRequest,
                Permission::MembersView,
            ],

            self::Auditor => [
                Permission::DashboardView,
                Permission::ProjectsView,
                Permission::TasksView,
                Permission::IssuesView,
                Permission::WorkflowsView,
                Permission::ApprovalsView,
                Permission::InventoryView,
                Permission::ReportsView, Permission::ReportsExport,
                Permission::MembersView,
                Permission::AuditView,
            ],
        };
    }

    public function allows(Permission $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }

    /**
     * @return list<array{value: string, label: string, description: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $role): array => [
            'value' => $role->value,
            'label' => $role->label(),
            'description' => $role->description(),
        ], self::cases());
    }
}
