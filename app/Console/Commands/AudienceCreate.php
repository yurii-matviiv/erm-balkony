<?php

namespace App\Console\Commands;

use App\Services\Audiences\CustomerMatchService;
use Illuminate\Console\Command;

class AudienceCreate extends Command
{
    protected $signature = 'audience:create {segment=qualified : qualified|unqualified|buyers} {--live : створити список реально (без --live лише перевірка validateOnly)}';

    protected $description = 'Створити список клієнтів (Customer Match) у Google Ads. Особисті дані не передаються.';

    public function handle(CustomerMatchService $service): int
    {
        try {
            $result = $service->createList((string) $this->argument('segment'), (bool) $this->option('live'));
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line(json_encode($result, JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
