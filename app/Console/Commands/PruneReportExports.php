<?php

namespace App\Console\Commands;

use App\Enums\ExportStatus;
use App\Models\ReportExport;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes export files past their expiry, and exports that never finished.
 * Crosses organizations on purpose: it only removes expired files.
 */
#[Signature('reports:prune-exports')]
#[Description('Delete report export files that have expired')]
class PruneReportExports extends Command
{
    public function handle(): int
    {
        $pruned = 0;

        ReportExport::withoutOrganizationScope()
            ->where(fn (Builder $exports) => $exports
                ->where('expires_at', '<=', now())
                // Anything stuck or failed for longer than an export is kept.
                ->orWhere(fn (Builder $stale) => $stale
                    ->where('status', '!=', ExportStatus::Completed->value)
                    ->where('created_at', '<=', now()->subDays(ReportExport::keepDays()))))
            ->orderBy('created_at')
            ->chunkById(200, function ($exports) use (&$pruned): void {
                foreach ($exports as $export) {
                    if ($export->disk !== null && $export->path !== null) {
                        Storage::disk($export->disk)->delete($export->path);
                    }

                    $export->delete();
                    $pruned++;
                }
            });

        $this->components->info("Pruned {$pruned} report ".($pruned === 1 ? 'export' : 'exports').'.');

        return self::SUCCESS;
    }
}
