<?php

namespace App\Console\Commands;

use App\Services\Audiences\CustomerMatchService;
use Illuminate\Console\Command;

class AudienceDelete extends Command
{
    protected $signature = 'audience:delete {segment} {--live : реально видалити список}';

    protected $description = 'Видалити список Customer Match у Google Ads (без --live лише validateOnly).';

    public function handle(CustomerMatchService $service): int
    {
        try {
            $this->line(json_encode($service->deleteList((string) $this->argument('segment'), (bool) $this->option('live')), JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
