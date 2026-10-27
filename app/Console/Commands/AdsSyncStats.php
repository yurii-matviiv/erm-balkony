<?php

namespace App\Console\Commands;

use App\Services\Ads\AdStatsSync;
use Illuminate\Console\Command;

class AdsSyncStats extends Command
{
    protected $signature = 'ads:sync-stats {--days=120 : скільки днів історії оновити}';

    protected $description = 'Оновити кампанії та денну статистику Google Ads (лише читання) для розподілу бюджету.';

    public function handle(AdStatsSync $sync): int
    {
        try {
            $this->line(json_encode($sync->run((int) $this->option('days'))));
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
