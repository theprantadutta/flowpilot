<?php

namespace App\Support\Reports;

use App\Enums\Feature;
use App\Enums\Permission;
use App\Enums\ReportType;
use App\Models\User;
use App\Support\Billing\Entitlements;
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
    public function __construct(private readonly Entitlements $entitlements) {}

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
     * Whether the member's role lets them see the report: reports.view plus
     * the permission for the data inside.
     */
    public function permits(User $user, ReportType $type): bool
    {
        return $user->can(Permission::ReportsView->value) && $user->can($type->permission()->value);
    }

    /**
     * Whether the organization's plan includes the report.
     */
    public function isIncluded(ReportType $type): bool
    {
        return $type->isBasic() || $this->entitlements->allows(Feature::AllReports);
    }

    public function canView(User $user, ReportType $type): bool
    {
        return $this->permits($user, $type) && $this->isIncluded($type);
    }

    public function canExport(User $user, ReportType $type): bool
    {
        return $this->canView($user, $type)
            && $user->can(Permission::ReportsExport->value)
            && $this->entitlements->allows(Feature::ReportExports);
    }

    /**
     * Reports the member's role allows, included in the plan or not.
     *
     * @return list<ReportType>
     */
    public function available(User $user): array
    {
        return array_values(array_filter(ReportType::cases(), fn (ReportType $type): bool => $this->permits($user, $type)));
    }
}
