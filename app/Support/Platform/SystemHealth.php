<?php

namespace App\Support\Platform;

use App\Support\Ai\AiProvider;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Live checks of what FlowPilot depends on: database, cache, queues, the
 * scheduler, storage, mail and the AI provider. Each check is quick and
 * never throws; a failure is reported, not raised.
 *
 * @phpstan-type Check array{key: string, label: string, status: 'ok'|'warning'|'failing', summary: string, detail: string|null}
 */
class SystemHealth
{
    /**
     * Cache key the scheduler refreshes every minute.
     */
    public const string HEARTBEAT_KEY = 'platform:scheduler-heartbeat';

    public function __construct(private readonly AiProvider $ai) {}

    /**
     * @return list<Check>
     */
    public function checks(): array
    {
        return [
            $this->database(),
            $this->cache(),
            $this->queues(),
            $this->scheduler(),
            $this->storage(),
            $this->mail(),
            $this->aiProvider(),
        ];
    }

    /**
     * @return Check
     */
    private function database(): array
    {
        try {
            $started = hrtime(true);
            DB::select('select 1');
            $milliseconds = (int) ((hrtime(true) - $started) / 1_000_000);
            $connection = DB::connection();

            return $this->check('database', 'Database', $milliseconds > 500 ? 'warning' : 'ok',
                "{$connection->getDriverName()} answered in {$milliseconds} ms",
                $milliseconds > 500 ? 'Every page runs several queries, so slow answers make the app feel slow.' : null);
        } catch (Throwable $exception) {
            return $this->check('database', 'Database', 'failing', 'Could not run a query', Str::limit($exception->getMessage(), 200));
        }
    }

    /**
     * @return Check
     */
    private function cache(): array
    {
        try {
            $key = 'platform:health:'.Str::random(8);
            Cache::put($key, 'ok', 30);
            $read = Cache::pull($key);

            return $read === 'ok'
                ? $this->check('cache', 'Cache', 'ok', 'Reads and writes work ('.config('cache.default').')')
                : $this->check('cache', 'Cache', 'failing', 'A value written could not be read back');
        } catch (Throwable $exception) {
            return $this->check('cache', 'Cache', 'failing', 'Could not write to the cache', Str::limit($exception->getMessage(), 200));
        }
    }

    /**
     * @return Check
     */
    private function queues(): array
    {
        try {
            $default = (string) config('queue.default');
            $waiting = Queue::connection($default)->size();
            $long = array_key_exists("{$default}-long", (array) config('queue.connections'))
                ? Queue::connection("{$default}-long")->size('long')
                : 0;
            $failed = DB::table((string) config('queue.failed.table', 'failed_jobs'))->count();

            $status = $failed > 0 || $waiting > 500 ? 'warning' : 'ok';

            return $this->check('queues', 'Queues', $status,
                "{$waiting} waiting, {$long} long-running waiting, {$failed} failed",
                $failed > 0 ? 'Failed jobs are listed under Failed jobs, where they can be retried.' : ($waiting > 500 ? 'Work is piling up. Check that workers are running.' : null));
        } catch (Throwable $exception) {
            return $this->check('queues', 'Queues', 'failing', 'Could not read the queues', Str::limit($exception->getMessage(), 200));
        }
    }

    /**
     * @return Check
     */
    private function scheduler(): array
    {
        $beat = Cache::get(self::HEARTBEAT_KEY);

        if (! is_string($beat)) {
            return $this->check('scheduler', 'Scheduler', 'failing', 'Has not run recently', 'Delays, reminders and trial checks only happen while the scheduler runs (php artisan schedule:work, or a cron entry for schedule:run).');
        }

        $minutes = (int) CarbonImmutable::parse($beat)->diffInMinutes(now());

        return $minutes > 5
            ? $this->check('scheduler', 'Scheduler', 'failing', "Last ran {$minutes} minutes ago", 'It should run every minute.')
            : $this->check('scheduler', 'Scheduler', 'ok', $minutes === 0 ? 'Ran in the last minute' : "Last ran {$minutes} minutes ago");
    }

    /**
     * @return Check
     */
    private function storage(): array
    {
        $disk = (string) config('flowpilot.uploads.disk', 'local');

        try {
            $path = 'health/'.Str::random(12).'.txt';
            Storage::disk($disk)->put($path, 'ok');
            $read = Storage::disk($disk)->get($path);
            Storage::disk($disk)->delete($path);

            return $read === 'ok'
                ? $this->check('storage', 'File storage', 'ok', "Files can be written to the {$disk} disk")
                : $this->check('storage', 'File storage', 'failing', "A file written to the {$disk} disk could not be read back");
        } catch (Throwable $exception) {
            return $this->check('storage', 'File storage', 'failing', "Could not write to the {$disk} disk", Str::limit($exception->getMessage(), 200));
        }
    }

    /**
     * @return Check
     */
    private function mail(): array
    {
        $mailer = (string) config('mail.default');

        return in_array($mailer, ['log', 'array'], true)
            ? $this->check('mail', 'Email', 'warning', "Mail goes to the {$mailer} driver", 'Invitations, resets and notifications are not delivered to inboxes.')
            : $this->check('mail', 'Email', 'ok', "Sending with {$mailer}");
    }

    /**
     * @return Check
     */
    private function aiProvider(): array
    {
        if (! $this->ai->isConfigured()) {
            return $this->check('ai', 'AI provider', 'warning', 'Not set up', 'The operations brief is switched off until the provider has its key.');
        }

        // Reachability is checked at most once a minute, without spending tokens.
        $reachable = Cache::remember('platform:health:ai', 60, function (): bool {
            try {
                return Http::timeout(5)->get(rtrim((string) config('ai.providers.freeway.url'), '/').'/health')->successful();
            } catch (Throwable) {
                return false;
            }
        });

        $provider = Str::headline($this->ai->name());

        return $reachable
            ? $this->check('ai', 'AI provider', 'ok', "{$provider} is reachable")
            : $this->check('ai', 'AI provider', 'failing', "{$provider} did not answer its health check", 'Briefs fall back to plain text until it answers again.');
    }

    /**
     * @param  'ok'|'warning'|'failing'  $status
     * @return Check
     */
    private function check(string $key, string $label, string $status, string $summary, ?string $detail = null): array
    {
        return compact('key', 'label', 'status', 'summary', 'detail');
    }
}
