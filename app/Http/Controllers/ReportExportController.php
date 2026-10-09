<?php

namespace App\Http\Controllers;

use App\Actions\Reports\RequestReportExport;
use App\Enums\ReportType;
use App\Http\Requests\Reports\RequestReportExportRequest;
use App\Models\ReportExport;
use App\Models\User;
use App\Support\Reports\ReportParameters;
use App\Support\Reports\ReportRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    public function __construct(private readonly ReportRegistry $reports) {}

    public function store(RequestReportExportRequest $request, ReportType $report, ReportParameters $parameters, RequestReportExport $requestExport): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($this->reports->canExport($user, $report), 403);

        $query = $parameters->query($this->reports->get($report), $request->parameters());

        $requestExport->handle($user, $report, $query);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Preparing your export. You will be notified when it is ready.']);

        return back();
    }

    /**
     * Only the member who asked for an export can download it, and only while
     * they may still export that report.
     */
    public function download(Request $request, ReportExport $export): StreamedResponse|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($export->user_id === $user->id && $this->reports->canExport($user, $export->report), 403);

        if (! $export->isDownloadable() || $export->disk === null || $export->path === null || ! Storage::disk($export->disk)->exists($export->path)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'That export has expired. Export the report again to get a fresh file.']);

            return to_route('reports.show', ['report' => $export->report->value]);
        }

        return Storage::disk($export->disk)->download($export->path, $export->filename ?? 'report.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
