<?php

namespace App\Console\Commands;

use App\Services\Seo\SeoReportService;
use Illuminate\Console\Command;

class SeoPositionsReport extends Command
{
    protected $signature = 'seo:positions-report';

    protected $description = 'Що змінилось в позиціях: останній знімок проти попереднього';

    public function handle(SeoReportService $svc): int
    {
        $r = $svc->changes();
        if (! $r['previous']) {
            $this->comment('Потрібно щонайменше два знімки (зараз: '.($r['current'] ?? 'жодного').').');

            return self::SUCCESS;
        }
        $this->info("Порівняння {$r['previous']} → {$r['current']}: змін ".count($r['events']));
        $this->table(['тип', 'що', 'зміна'], array_map(fn ($e) => [$e['type'], $e['label'], $e['text']], $r['events']));

        return self::SUCCESS;
    }
}
