<?php

namespace App\Enums;

/**
 * Every kind of notification FlowPilot sends. In-app notifications are always
 * recorded; whether each type is also emailed is a preference.
 */
enum NotificationType: string
{
    case ApprovalRequired = 'approval_required';
    case ApprovalDecided = 'approval_decided';
    case TaskAssigned = 'task_assigned';
    case TaskOverdue = 'task_overdue';
    case IssueAssigned = 'issue_assigned';
    case ProjectUpdated = 'project_updated';
    case WorkflowFailed = 'workflow_failed';
    case WorkflowCompleted = 'workflow_completed';
    case WorkflowMessage = 'workflow_message';
    case InventoryLow = 'inventory_low';
    case PurchaseRequestUpdated = 'purchase_request_updated';
    case MemberJoined = 'member_joined';

    public function label(): string
    {
        return match ($this) {
            self::ApprovalRequired => 'Approval needed from you',
            self::ApprovalDecided => 'Your request was decided',
            self::TaskAssigned => 'Task assigned to you',
            self::TaskOverdue => 'Your task is overdue',
            self::IssueAssigned => 'Issue assigned to you',
            self::ProjectUpdated => 'Project you are on changed status',
            self::WorkflowFailed => 'Workflow run failed',
            self::WorkflowCompleted => 'Workflow you started finished',
            self::WorkflowMessage => 'Message sent by a workflow',
            self::InventoryLow => 'Stock below reorder point',
            self::PurchaseRequestUpdated => 'Your purchase request moved on',
            self::MemberJoined => 'Someone joined the organization',
        };
    }

    /**
     * The settings group the type is listed under.
     */
    public function group(): string
    {
        return match ($this) {
            self::ApprovalRequired, self::ApprovalDecided => 'Approvals',
            self::TaskAssigned, self::TaskOverdue, self::IssueAssigned, self::ProjectUpdated => 'Work',
            self::WorkflowFailed, self::WorkflowCompleted, self::WorkflowMessage => 'Workflows',
            self::InventoryLow, self::PurchaseRequestUpdated => 'Inventory',
            self::MemberJoined => 'Team',
        };
    }

    /**
     * Whether the type is emailed when neither the member nor the organization
     * has said otherwise. Things that block someone else's work default to on.
     */
    public function emailByDefault(): bool
    {
        return match ($this) {
            self::ApprovalRequired, self::ApprovalDecided, self::TaskAssigned, self::TaskOverdue,
            self::IssueAssigned, self::WorkflowFailed, self::WorkflowMessage, self::InventoryLow, self::PurchaseRequestUpdated => true,
            self::ProjectUpdated, self::WorkflowCompleted, self::MemberJoined => false,
        };
    }

    /**
     * @return list<array{value: string, label: string, group: string, email_by_default: bool}>
     */
    public static function options(): array
    {
        return array_map(fn (self $type): array => [
            'value' => $type->value,
            'label' => $type->label(),
            'group' => $type->group(),
            'email_by_default' => $type->emailByDefault(),
        ], self::cases());
    }
}
