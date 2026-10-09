<?php

namespace App\Jobs;

use App\Enums\ExportStatus;
use App\Jobs\Concerns\RunsOnLongQueue;
use App\Models\Organization;
use App\Models\ReportExport;
use App\Notifications\ReportExportFinishedNotification;
use App\Support\Reports\CsvWriter;
use App\Support\Reports\ReportQuery;
use App\Support\Reports\ReportRegistry;
use App\Support\Tenancy\Tenancy;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Writes a report export to a private file and tells the member it is ready.
 */
class GenerateReportExport implements ShouldQueue
{
    use Queueable, RunsOnLongQueue;

    public int $tries = 2;

    public int $timeout = 900;

    public function __construct(
        public string $organizationId,
        public string $exportId,
    ) {
        $this->onLongQueue();
    }

    public function handle(Tenancy $tenancy, ReportRegistry $reports, CsvWriter $writer): void
    {
        $organization = Organization::query()->find($this->organizationId);

        if ($organization === null || ! $organization->isActive()) {
            return;
        }

        $tenancy->run($organization, function () use ($organization, $tenancy, $reports, $writer): void {
            $export = ReportExport::query()->with('user')->find($this->exportId);

            if ($export === null || $export->status->isFinished()) {
                return;
            }

            // The member may have lost access since asking; read as them.
            $membership = $tenancy->membershipFor($export->user);

            if ($membership === null || ! $membership->isActive()) {
                $this->markFailed($export, 'The member who asked for this export no longer belongs to the organization.', notify: false);

                return;
            }

            $tenancy->run($organization, function () use ($organization, $export, $reports, $writer): void {
                if (! $reports->canExport($export->user, $export->report)) {
                    $this->markFailed($export, 'Exporting this report needs permissions the member no longer has.');

                    return;
                }

                $export->forceFill(['status' => ExportStatus::Processing, 'started_at' => now()])->save();

                $report = $reports->get($export->report);
                $query = ReportQuery::fromArray($export->parameters, $organization->timezone);
                $disk = (string) config('flowpilot.exports.disk', 'local');
                $path = "exports/{$organization->id}/{$export->id}.csv";

                $stream = fopen('php://temp', 'w+b') ?: throw new RuntimeException('Could not open a temporary stream for the export.');

                try {
                    $rows = $writer->write($stream, $report->columns($query), $report->rows($query), $organization->timezone);
                    rewind($stream);
                    Storage::disk($disk)->writeStream($path, $stream);
                } finally {
                    fclose($stream);
                }

                $export->forceFill([
                    'status' => ExportStatus::Completed,
                    'disk' => $disk,
                    'path' => $path,
                    'filename' => sprintf('%s-%s-to-%s.csv', $export->report->value, $query->from->toDateString(), $query->to->toDateString()),
                    'row_count' => $rows,
                    'size' => Storage::disk($disk)->size($path),
                    'completed_at' => now(),
                    'expires_at' => now()->addDays(ReportExport::keepDays()),
                ])->save();

                $export->user->notify(new ReportExportFinishedNotification($export));
            }, $membership);
        });
    }

    /**
     * After the last attempt: record the failure and tell the member.
     */
    public function failed(?Throwable $exception): void
    {
        $organization = Organization::query()->find($this->organizationId);

        if ($organization === null) {
            return;
        }

        app(Tenancy::class)->run($organization, function () use ($exception): void {
            $export = ReportExport::query()->with('user')->find($this->exportId);

            if ($export !== null && ! $export->status->isFinished()) {
                Log::warning('Report export failed', ['export' => $export->id, 'error' => $exception?->getMessage()]);
                $this->markFailed($export, 'The export could not be written.');
            }
        });
    }

    private function markFailed(ReportExport $export, string $reason, bool $notify = true): void
    {
        $export->forceFill(['status' => ExportStatus::Failed, 'error' => $reason, 'completed_at' => now()])->save();

        if ($notify) {
            $export->user->notify(new ReportExportFinishedNotification($export));
        }
    }
}
