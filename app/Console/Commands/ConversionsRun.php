<?php

namespace App\Console\Commands;

use App\Services\Conversions\ConversionDispatcher;
use App\Services\Conversions\ConversionQueue;
use App\Services\Conversions\ConversionSettings;
use Illuminate\Console\Command;

class ConversionsRun extends Command
{
    protected $signature = 'conversions:run';

    protected $description = 'Планувальник офлайн-конверсій Google Ads: оновити чергу, відправити готові, перевірити статуси';

    public function handle(ConversionQueue $queue, ConversionDispatcher $dispatcher): int
    {
        $mode = ConversionSettings::mode();

        if ($mode === 'off') {
            return self::SUCCESS;
        }

        if (ConversionSettings::baselineAt() === null) {
            $this->warn('Спершу виконайте: php artisan conversions:baseline');

            return self::SUCCESS;
        }

        $this->line('queue: '.json_encode($queue->sync()));
        $this->line('send ('.$mode.'): '.json_encode($dispatcher->dispatchDue($mode)));

        if ($mode === 'live') {
            $this->line('status: '.json_encode($dispatcher->checkStatuses()));
        }

        return self::SUCCESS;
    }
}
