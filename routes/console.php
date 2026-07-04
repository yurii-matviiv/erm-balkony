<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Auto-sync from the legacy DB — runs every minute when enabled.
 *
 * The --scheduled flag tells the command to exit silently when the
 * admin has turned off auto-sync via the "Синхронізація" page toggle,
 * so no extra conditional is needed here.
 *
 * withoutOverlapping() prevents a second instance from starting if the
 * previous run is still in progress (can happen with large datasets).
 *
 * To activate the scheduler on the server, add to crontab:
 *   * * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
 */
Schedule::command('sync:legacy --scheduled')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();
