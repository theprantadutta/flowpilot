<?php

namespace App\Actions\Reports;

use App\Enums\ExportStatus;
use App\Enums\ReportType;
use App\Jobs\GenerateReportExport;
use App\Models\ReportExport;
use App\Models\User;
use App\Support\Activity\ActivityLogger;
use App\Support\Reports\ReportQuery;
use App\Support\Tenancy\Tenancy;

class RequestReportExport
{
    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly Tenancy $tenancy,
    ) {}

    /**
     * Queue an export of a report as it is currently filtered. Data leaving
     * FlowPilot is recorded in the activity log.
     */
    public function handle(User $user, ReportType $type, ReportQuery $query): ReportExport
    {
        $export = ReportExport::query()->create([
            'user_id' => $user->id,
            'report' => $type,
            'format' => 'csv',
            'parameters' => [...$query->toArray(), 'label' => $query->label()],
            'status' => ExportStatus::Queued,
        ]);

        $this->activity->log('report.exported', $export, [
            'report' => $type->label(),
            'range' => $query->label(),
            'filters' => $query->filters,
        ], actor: $user);

        GenerateReportExport::dispatch($this->tenancy->currentOrFail()->id, $export->id)->afterCommit();

        return $export;
    }
}
