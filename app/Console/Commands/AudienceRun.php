<?php

namespace App\Console\Commands;

use App\Services\Audiences\CustomerMatchService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AudienceRun extends Command
{
    protected $signature = 'audience:run';

    protected $description = 'Щоденно: класифікувати клієнтів і відправити зміни в списки Customer Match (qualified, unqualified, buyers). Нічого не робить без CUSTOMER_MATCH_CONFIRMED=true.';

    public function handle(CustomerMatchService $service): int
    {
        if (! config('customer_match.confirmed')) {
            return self::SUCCESS;
        }

        $this->call('audience:classify');

        foreach (['qualified', 'unqualified', 'buyers'] as $segment) {
            try {
                $stats = $service->sync($segment, true);
                Log::info('audience:run', $stats);
            } catch (\Throwable $e) {
                // один збій не зупиняє інші списки
                Log::error('audience:run '.$segment.': '.$e->getMessage());
                $this->error($segment.': '.$e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
