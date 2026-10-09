<?php

namespace App\Notifications;

use App\Enums\ExportStatus;
use App\Enums\NotificationType;
use App\Models\ReportExport;
use Illuminate\Support\Number;

class ReportExportFinishedNotification extends TenantNotification
{
    public string $exportId;

    public string $reportSlug;

    public string $reportName;

    public bool $succeeded;

    public int $rows;

    public string $range;

    public function __construct(ReportExport $export)
    {
        parent::__construct();

        $this->exportId = $export->id;
        $this->reportSlug = $export->report->value;
        $this->reportName = $export->report->label();
        $this->succeeded = $export->status === ExportStatus::Completed;
        $this->rows = (int) $export->row_count;
        $this->range = is_string($export->parameters['label'] ?? null) ? $export->parameters['label'] : '';
    }

    public function type(): NotificationType
    {
        return NotificationType::ReportExportFinished;
    }

    public function title(): string
    {
        return $this->succeeded
            ? "Your {$this->reportName} export is ready"
            : "Your {$this->reportName} export could not be made";
    }

    public function body(): ?string
    {
        if (! $this->succeeded) {
            return 'Something went wrong while preparing the file. Try again, or choose a shorter date range.';
        }

        $rows = Number::format($this->rows).' '.($this->rows === 1 ? 'row' : 'rows');
        $range = $this->range !== '' ? " for {$this->range}" : '';

        return "{$rows}{$range}. The file can be downloaded for ".ReportExport::keepDays().' days.';
    }

    public function url(): ?string
    {
        return $this->succeeded
            ? $this->tenantRoute('reports.exports.download', ['export' => $this->exportId])
            : $this->tenantRoute('reports.show', ['report' => $this->reportSlug]);
    }

    public function tone(): string
    {
        return $this->succeeded ? 'success' : 'danger';
    }

    public function actionText(): string
    {
        return $this->succeeded ? 'Download CSV' : 'Open report';
    }
}
