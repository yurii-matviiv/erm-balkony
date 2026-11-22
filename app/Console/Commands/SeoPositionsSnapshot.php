<?php

namespace App\Console\Commands;

use App\Services\Seo\SeoSnapshotService;
use Illuminate\Console\Command;

class SeoPositionsSnapshot extends Command
{
    protected $signature = 'seo:positions-snapshot
        {--dry : Лише показати, нічого не писати в БД}
        {--only= : positions (за замовчуванням) або index (стан індексації нових сторінок)}
        {--offset=0 : для index: з якої адреси}
        {--limit=5 : для index: скільки адрес за виклик}
        {--refresh-top : перебрати склад топ-30 (раз на місяць, лише свідомо)}';

    protected $description = 'Знімок позицій Google (Search Console API, лише читання) у seo_position_snapshots';

    public function handle(SeoSnapshotService $svc): int
    {
        if ($this->option('only') === 'index') {
            $res = $svc->storeIndexStatus((int) $this->option('offset'), (int) $this->option('limit'));
            foreach ($res as $url => $state) {
                $this->line("$url — ".($state ?? 'невідомо'));
            }

            return self::SUCCESS;
        }

        $rows = $svc->collect((bool) $this->option('refresh-top'), ! $this->option('dry'));
        $by = collect($rows)->groupBy('scope')->map->count();
        $this->info('Період: '.implode(' — ', $svc->period()).'. Рядків: '.$by->map(fn ($n, $k) => "$k=$n")->implode(', '));

        if ($this->option('dry')) {
            $this->table(['scope', 'page', 'query', 'покази', 'кліки', 'поз.'],
                collect($rows)->whereIn('scope', ['new_page'])->map(fn ($r) => [$r['scope'], $r['page'], $r['query'], $r['impressions'], $r['clicks'], $r['position']])->all());
            $this->comment('--dry: у БД нічого не записано.');

            return self::SUCCESS;
        }

        $this->info('Записано: '.$svc->store($rows));

        return self::SUCCESS;
    }
}
