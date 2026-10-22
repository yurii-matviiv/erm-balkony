<?php

namespace App\Console\Commands;

use App\Services\Audiences\CustomerMatchService;
use Illuminate\Console\Command;

class AudienceSync extends Command
{
    protected $signature = 'audience:sync {segment=qualified : qualified|unqualified|buyers} {--live : реальна відправка (потрібен CUSTOMER_MATCH_CONFIRMED=true)} {--count : лише порахувати, нічого не відправляти в Google}';

    protected $description = 'Передати клієнтів (хеш телефону/email) у список Customer Match через Data Manager API. За замовчуванням лише validateOnly.';

    public function handle(CustomerMatchService $service): int
    {
        $segment = (string) $this->argument('segment');

        try {
            if ($this->option('count')) {
                $data = $service->collect($segment);
                $this->line(json_encode([
                    'segment' => $segment,
                    'clients' => $data['clients'],
                    'members' => count($data['members']),
                    'without_identifier' => $data['without_identifier'],
                ], JSON_UNESCAPED_UNICODE));

                return self::SUCCESS;
            }

            $this->line(json_encode($service->sync($segment, (bool) $this->option('live')), JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
