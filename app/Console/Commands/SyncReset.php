<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * php artisan sync:reset
 *
 * Truncates all tables that are populated by SyncMappers (in the correct
 * dependency order, foreign key checks disabled), then immediately runs
 * sync:legacy to re-import everything from the legacy DB with correct IDs.
 *
 * Use this when the ID-preservation logic changes (e.g. adding persistRow()
 * to ClientsSyncMapper / LeadsSyncMapper) and existing rows already have
 * wrong auto-increment IDs that UPDATE cannot fix.
 *
 * Safe to run any number of times during development. NOT for production
 * once real (non-legacy) data exists in these tables.
 */
class SyncReset extends Command
{
    protected $signature   = 'sync:reset {--yes : Skip confirmation prompt}';
    protected $description = 'Truncate all synced tables and re-run sync:legacy from scratch';

    /**
     * Tables to truncate, in dependency order (children before parents).
     * Foreign key checks are disabled so the order doesn't strictly matter,
     * but it's cleaner to document the dependency chain here.
     */
    private const TABLES = [
        // order children
        'order_files',
        'order_payments',
        // lead children
        'lead_marketing_data',
        'lead_lead_service_type',
        // main entities
        'orders',
        'leads',
        'clients',
    ];

    public function handle(): int
    {
        if (! $this->option('yes')) {
            $list = implode(', ', self::TABLES);
            if (! $this->confirm("This will TRUNCATE: {$list}\nThen re-run sync:legacy. Continue?")) {
                $this->line('Aborted.');
                return self::SUCCESS;
            }
        }

        $this->info('Truncating tables…');

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        foreach (self::TABLES as $table) {
            DB::table($table)->truncate();
            $this->line("  ✓ {$table}");
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->newLine();
        $this->info('Running sync:legacy…');
        $this->newLine();

        return $this->call('sync:legacy');
    }
}
