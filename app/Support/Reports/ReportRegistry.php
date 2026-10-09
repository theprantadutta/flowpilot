<?php

namespace App\Support\Reports;

use App\Enums\Permission;
use App\Enums\ReportType;
use App\Models\User;
use App\Support\Reports\Definitions\ActivityReport;
use App\Support\Reports\Definitions\ApprovalTurnaroundReport;
use App\Support\Reports\Definitions\InventoryStatusReport;
use App\Support\Reports\Definitions\OrganizationUsageReport;
use App\Support\Reports\Definitions\ProjectProgressReport;
use App\Support\Reports\Definitions\TaskCompletionReport;
use App\Support\Reports\Definitions\WorkflowExecutionReport;
use App\Support\Reports\Definitions\WorkflowFailuresReport;

class ReportRegistry
{
    /**
     * @var array<string, class-string<Report>>
     */
    private const array REPORTS = [
        'project-progress' => ProjectProgressReport::class,
        'task-completion' => TaskCompletionReport::class,
        'approval-turnaround' => ApprovalTurnaroundReport::class,
        'workflow-execution' => WorkflowExecutionReport::class,
        'workflow-failures' => WorkflowFailuresReport::class,
        'inventory-status' => InventoryStatusReport::class,
        'activity' => ActivityReport::class,
        'organization-usage' => OrganizationUsageReport::class,
    ];

    public function get(ReportType $type): Report
    {
        return app(self::REPORTS[$type->value]);
    }

    /**
     * Reports need reports.view plus the permission for the data inside.
     */
    public function canView(User $user, ReportType $type): bool
    {
        return $user->can(Permission::ReportsView->value) && $user->can($type->permission()->value);
    }

    public function canExport(User $user, ReportType $type): bool
    {
        return $this->canView($user, $type) && $user->can(Permission::ReportsExport->value);
    }

    /**
     * @return list<ReportType>
     */
    public function available(User $user): array
    {
        return array_values(array_filter(ReportType::cases(), fn (ReportType $type): bool => $this->canView($user, $type)));
    }
}
