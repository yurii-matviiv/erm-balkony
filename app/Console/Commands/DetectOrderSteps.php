<?php

namespace App\Console\Commands;

use App\Services\Orders\OrderStepDetector;
use Illuminate\Console\Command;

class DetectOrderSteps extends Command
{
    protected $signature = 'steps:detect-orders';

    protected $description = 'Журнал кроків замовлень: аванс, оплата повністю, виготовлено, монтаж — за фактами в даних (див. OrderStepDetector)';

    public function handle(OrderStepDetector $detector): int
    {
        $this->line(json_encode($detector->run(), JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
