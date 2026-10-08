<?php

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
