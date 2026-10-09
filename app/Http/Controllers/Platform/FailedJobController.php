<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Background jobs that gave up, with what went wrong, to retry or clear.
 */
class FailedJobController extends Controller
{
    public function __construct(private readonly FailedJobProviderInterface $failer) {}

    public function index(): Response
    {
        $jobs = collect($this->failer->all())
            ->take(100)
            ->map(function (object $job): array {
                $payload = json_decode((string) ($job->payload ?? '{}'), true);
                $exception = (string) ($job->exception ?? '');

                return [
                    'id' => (string) ($job->uuid ?? $job->id ?? ''),
                    'job' => is_array($payload) && is_string($payload['displayName'] ?? null) ? class_basename($payload['displayName']) : 'Unknown job',
                    'connection' => (string) ($job->connection ?? ''),
                    'queue' => (string) ($job->queue ?? ''),
                    'error' => Str::limit(Str::before($exception, "\n"), 300),
                    'failed_at' => is_string($job->failed_at ?? null) ? $job->failed_at : null,
                ];
            })
            ->values();

        return Inertia::render('platform/FailedJobs', ['jobs' => $jobs]);
    }

    public function retry(Request $request, string $uuid): RedirectResponse
    {
        abort_if($this->failer->find($uuid) === null, 404);

        Artisan::call('queue:retry', ['id' => [$uuid]]);
        $this->audit($request, 'retried', $uuid);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Job sent back to the queue.']);

        return back();
    }

    public function destroy(Request $request, string $uuid): RedirectResponse
    {
        abort_unless($this->failer->forget($uuid), 404);
        $this->audit($request, 'cleared', $uuid);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Failed job cleared.']);

        return back();
    }

    private function audit(Request $request, string $what, string $uuid): void
    {
        /** @var User $admin */
        $admin = $request->user();

        Log::info("Platform admin {$what} a failed job", ['admin' => $admin->id, 'job' => $uuid]);
    }
}
