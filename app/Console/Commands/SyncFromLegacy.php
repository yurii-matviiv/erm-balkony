<?php

namespace App\Console\Commands;

use App\Models\AppSetting;
use App\Services\Sync\SyncMapperRegistry;
use Illuminate\Console\Command;

/**
 * php artisan sync:legacy
 *
 * Runs every registered SyncMapper in order and reports per-mapper
 * statistics (created / updated / skipped). Safe to run multiple times —
 * all mappers use idempotent upserts keyed on legacy_id.
 *
 * Used in two ways:
 *   1. Manually from the admin UI ("Синхронізувати все зараз" button).
 *   2. Automatically by the scheduler every minute when auto-sync is ON.
 *
 * The --scheduled flag causes the command to exit early (silently, exit 0)
 * if sync_auto_enabled is false — so the scheduler registration stays
 * simple (no conditional) and the toggle lives purely in the DB.
 *
 * Sync INTERVAL is a setting too (sync_interval_minutes, default 1):
 * the scheduler still ticks every minute, but a --scheduled run also
 * exits early while less than that many minutes passed since the last
 * completed run. This lets the admin slow the sync down to e.g. once an
 * hour during quiet development and speed it back up to every minute
 * when managers start working in the system — no crontab/deploy changes,
 * just the field on the "Синхронізація" page. Manual runs (the button /
 * plain `php artisan sync:legacy`) ignore the interval on purpose.
 *
 * Conflict policy: new-system wins.
 * If a record already existed in the new DB and was modified there AFTER
 * the last sync, the mapper will still overwrite it on the next run.
 * Full conflict resolution (new-wins locking) is a future enhancement.
 */
class SyncFromLegacy extends Command
{
    protected $signature = 'sync:legacy
                            {--scheduled : Exit silently if auto-sync is disabled}';

    protected $description = 'Sync all legacy-DB tables into the new DB via registered SyncMappers';

    public function handle(): int
    {
        // When invoked by the scheduler, respect the admin toggle.
        if ($this->option('scheduled') && ! AppSetting::getBool('sync_auto_enabled', false)) {
            return self::SUCCESS;
        }

        // ...and the configured interval: skip quietly until enough
        // minutes have passed since the previous COMPLETED run. The
        // timestamp is written at the end of handle(), so a long run
        // naturally pushes the next one back — no overlap on top of
        // the scheduler's own withoutOverlapping().
        if ($this->option('scheduled')) {
            $interval = max(1, (int) AppSetting::get('sync_interval_minutes', '1'));
            $lastRun = AppSetting::get('sync_last_run_at');

            // Carbon 3 diffs are SIGNED: base->diffInMinutes(later) is
            // positive, the reverse is negative — keep lastRun as base.
            if ($lastRun !== null && \Carbon\Carbon::parse($lastRun)->diffInMinutes(now()) < $interval) {
                return self::SUCCESS;
            }
        }

        $mappers = SyncMapperRegistry::all();

        if (empty($mappers)) {
            $this->warn('No mappers registered in SyncMapperRegistry.');
            return self::SUCCESS;
        }

        $totalCreated = 0;
        $totalUpdated = 0;
        $totalSkipped = 0;

        foreach ($mappers as $mapper) {
            $label = $mapper->label();
            $this->info("→ {$label}");

            try {
                $result = $mapper->run();

                $totalCreated += $result['created'];
                $totalUpdated += $result['updated'];
                $totalSkipped += $result['skipped'];

                $this->line(
                    "  created: {$result['created']}  updated: {$result['updated']}  skipped: {$result['skipped']}"
                );
            } catch (\Throwable $e) {
                $this->error("  FAILED: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("Done. Total: created={$totalCreated}  updated={$totalUpdated}  skipped={$totalSkipped}");

        // Persist the timestamp so the UI can show "last synced X ago".
        AppSetting::set('sync_last_run_at', now()->toIso8601String());

        return self::SUCCESS;
    }
}
