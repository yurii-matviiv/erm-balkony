<?php

namespace App\Console\Commands;

use App\Services\Conversions\ConversionQueue;
use Illuminate\Console\Command;

class ConversionsBaseline extends Command
{
    protected $signature = 'conversions:baseline';

    protected $description = 'Одноразово: позначити все, що кваліфіковане зараз, як baseline (не відправляти повторно). Пише лише в нову БД.';

    public function handle(ConversionQueue $queue): int
    {
        $result = $queue->baseline();

        $this->info("Baseline готово: кваліфікованих заявок {$result['qualified']}, продажів {$result['sale']}.");

        return self::SUCCESS;
    }
}
