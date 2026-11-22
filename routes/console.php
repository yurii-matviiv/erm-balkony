<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Auto-sync from the legacy DB — runs every 5 minutes when enabled.
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
// LEGACY-CLEANUP: синхронізація зі старою CRM; прибрати після повного переходу на нову базу.
Schedule::command('sync:legacy --scheduled')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

/*
 * Push reminders for managers (incl. «Заміри»). Exits in milliseconds when nothing is due.
 */
Schedule::command('reminders:dispatch')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

/*
 * Журнал кроків замовлень за фактами в даних (аванс, оплата повністю, виготовлено, монтаж). Платежі й дати
 * приходять синхронізацією напряму в БД, тому окрема перевірка, а не хук моделі. Не впливає на конверсії Google.
 */
Schedule::command('steps:detect-orders')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

/*
 * Offline conversions -> Google Ads (Data Manager API).
 * Exits immediately while mode = off (default). Modes: php artisan conversions:mode.
 */
Schedule::command('conversions:run')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->runInBackground();

/*
 * Customer Match lists (Google Ads): classify clients and upload only changes.
 * Once a day at night, at a minute that does not coincide with the 5/15-minute
 * jobs above; withoutOverlapping guards against a second instance.
 */
Schedule::command('audience:run')
    ->dailyAt('03:22')
    ->withoutOverlapping()
    ->runInBackground();

/*
 * Google Ads stats for the budget-allocation page (read-only). Daily, at an odd minute
 * that does not coincide with the other jobs.
 */
Schedule::command('ads:sync-stats')
    ->dailyAt('04:10')
    ->withoutOverlapping()
    ->runInBackground();

/*
 * SEO: щопонеділка знімок позицій Google (Search Console API, лише читання) і стан індексації нових сторінок.
 * Дані Google приходять із затримкою ~3 дні, тому частіше за раз на тиждень знімати немає сенсу.
 */
Schedule::command('seo:positions-snapshot')
    ->weeklyOn(1, '08:20')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('seo:positions-snapshot --only=index --limit=100')
    ->weeklyOn(1, '08:40')
    ->withoutOverlapping()
    ->runInBackground();
