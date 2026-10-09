<?php

use App\Support\Platform\SystemHealth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

/*
| Scheduled work. Run `php artisan schedule:work` locally, or a single
| scheduler container / cron entry in production.
*/

// Hourly, so each organization hears about overdue work soon after midnight in its own timezone.
Schedule::command('tasks:notify-overdue')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

// Wakes workflow runs whose delay or retry is due, and recovers runs a stopped worker left behind.
Schedule::command('workflows:resume')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();

// Overdue approvals: expire the ones set to reject when overdue, remind approvers about the rest.
Schedule::command('approvals:check-overdue')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// Report exports are kept for a week; delete the files after that.
Schedule::command('reports:prune-exports')
    ->daily()
    ->withoutOverlapping()
    ->onOneServer();

// Gives up on AI briefs a stopped worker left half written; deletes old ones.
Schedule::command('ai:prune-briefs')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

// Reminds owners before a trial ends and moves ended trials to Free.
Schedule::command('billing:check-trials')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

// Lets platform health see that the scheduler is running.
Schedule::call(fn () => Cache::put(SystemHealth::HEARTBEAT_KEY, now()->toIso8601String(), 600))
    ->everyMinute()
    ->name('scheduler-heartbeat')
    ->onOneServer();
