<?php

namespace App\Services\Seo;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Усе про одне ключове слово: свіжі дані Google (Search Console, лише читання, кеш 15 хв),
 * історія зі знімків у БД і зв'язок зі сторінками та заявками/замовленнями CRM.
 */
class SeoKeywordService
{
    public function __construct(private SearchConsoleService $sc, private SeoSnapshotService $snap) {}

    /** @return array{error?: string, period: array, total: array, prev: array, pages: list<array>, days: list<array>, devices: list<array>, countries: list<array>, related: list<array>} */
    public function live(string $keyword): array
    {
        // Кеш 15 хв: щоб не дзвонити в Google на кожне перемикання вкладки.
        $key = 'seo.kw.'.sha1(mb_strtolower($keyword)).'.'.now()->format('Ymd').'.'.intdiv((int) now()->format('Hi'), 15);

        if ($hit = Cache::get($key)) {
            return $hit;
        }

        try {
            $data = $this->fetch($keyword);
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()] + $this->empty();
        }

        Cache::put($key, $data, 900);

        return $data;
    }

    /** @return array<string,mixed> */
    private function fetch(string $kw): array
    {
        $p = $this->snap->period();
        $prevEnd = \Illuminate\Support\Carbon::parse($p['start'])->subDay();
        $prevStart = $prevEnd->copy()->subDays((int) config('seo.window_days') - 1);
        $f = [['dimension' => 'query', 'operator' => 'equals', 'expression' => $kw]];

        $total = $this->sc->query($p['start'], $p['end'], [], 1, $f)[0] ?? null;
        $prev = $this->sc->query($prevStart->toDateString(), $prevEnd->toDateString(), [], 1, $f)[0] ?? null;
        $pages = $this->sc->query($p['start'], $p['end'], ['page'], 25, $f);
        $days = $this->sc->query($p['start'], $p['end'], ['date'], 100, $f);
        $devices = $this->sc->query($p['start'], $p['end'], ['device'], 10, $f);
        $countries = $this->sc->query($p['start'], $p['end'], ['country'], 25, $f);

        // Схожі запити: що ще шукають ті, хто приходить на головну сторінку цього слова.
        $related = [];
        if ($pages) {
            $top = $pages[0]['keys'][0];
            $related = $this->sc->query($p['start'], $p['end'], ['query'], 30, [
                ['dimension' => 'page', 'operator' => 'equals', 'expression' => $top],
            ]);
        }

        $m = fn (?array $r): array => $r ? $this->m($r) : ['impressions' => 0, 'clicks' => 0, 'ctr' => 0.0, 'position' => null];

        return [
            'period' => $p + ['prev_start' => $prevStart->toDateString(), 'prev_end' => $prevEnd->toDateString()],
            'total' => $m($total),
            'prev' => $m($prev),
            'pages' => array_map(fn ($r) => ['label' => $r['keys'][0], 'url' => $r['keys'][0]] + $this->m($r), $pages),
            'days' => array_map(fn ($r) => ['label' => $r['keys'][0]] + $this->m($r), $days),
            'devices' => array_map(fn ($r) => ['label' => 'Пристрій: '.$this->deviceName($r['keys'][0])] + $this->m($r), $devices),
            'countries' => array_map(fn ($r) => ['label' => 'Країна: '.strtoupper($r['keys'][0])] + $this->m($r), $countries),
            'related' => array_values(array_filter(array_map(
                fn ($r) => mb_strtolower($r['keys'][0]) === mb_strtolower($kw) ? null : ['label' => $r['keys'][0], 'keyword' => $r['keys'][0]] + $this->m($r),
                $related,
            ))),
        ];
    }

    /** Історія слова зі знімків (позиція, покази, кліки) + сторінки, зафіксовані знімками. */
    public function history(string $keyword): array
    {
        $rows = DB::table('seo_position_snapshots')->whereIn('scope', ['top_query', 'query_all'])->where('query', $keyword)->orderBy('snapshot_date')->get()
            ->unique('snapshot_date')->values();
        $pages = DB::table('seo_position_snapshots')->where('scope', 'query_page')->where('query', $keyword)->orderBy('snapshot_date')->get()->groupBy('snapshot_date');

        $prev = null;
        $out = [];
        foreach ($rows as $r) {
            $delta = ($prev && $prev->position !== null && $r->position !== null && $r->impressions >= config('seo.min_impressions') && $prev->impressions >= config('seo.min_impressions'))
                ? round((float) $r->position - (float) $prev->position, 1) : null;
            $top = isset($pages[$r->snapshot_date]) ? $pages[$r->snapshot_date]->sortByDesc('impressions')->first() : null;
            $out[] = [
                'label' => \Illuminate\Support\Carbon::parse($r->snapshot_date)->format('d.m.Y'),
                'impressions' => (int) $r->impressions, 'clicks' => (int) $r->clicks, 'ctr' => (float) $r->ctr,
                'position' => $r->position, 'delta' => $delta,
                'sub' => $top ? 'Головна сторінка: '.$this->path($top->page) : ($r->page ? 'Головна сторінка: '.$this->path($r->page) : null),
            ];
            $prev = $r;
        }

        return array_reverse($out);
    }

    /**
     * Заявки й замовлення CRM зі сторінок, на яких слово ранжується (за адресою сторінки в маркетингових даних заявки).
     * Це НЕ заявки «саме по цьому слову»: Google не передає слово в заявку, лише сторінку.
     *
     * @param  list<string>  $pageUrls
     * @return list<array<string,mixed>>
     */
    public function crm(array $pageUrls): array
    {
        $paths = collect($pageUrls)->mapWithKeys(fn ($u) => [$this->path($u) => $u]);
        if ($paths->isEmpty()) {
            return [];
        }

        $rows = DB::table('lead_marketing_data as m')
            ->leftJoin('orders as o', 'o.lead_id', '=', 'm.lead_id')
            ->where(function ($q) use ($paths) {
                foreach ($paths->keys() as $path) {
                    $q->orWhere('m.utm_url', 'like', '%'.$path.'%');
                }
            })
            ->get(['m.lead_id', 'm.utm_url', 'o.id as order_id', 'o.total_price']);

        $by = [];
        foreach ($rows as $r) {
            $path = $this->path((string) $r->utm_url);
            if (! $paths->has($path)) {
                continue; // /vikna не має збирати /vikna-salamander
            }
            $by[$path]['leads'][$r->lead_id] = true;
            if ($r->order_id) {
                $by[$path]['orders'][$r->order_id] = (float) $r->total_price;
            }
        }

        return $paths->map(fn ($url, $path) => [
            'label' => $url, 'url' => $url,
            'leads' => count($by[$path]['leads'] ?? []),
            'orders' => count($by[$path]['orders'] ?? []),
            'turnover' => round(array_sum($by[$path]['orders'] ?? [])),
        ])->values()->all();
    }

    public function path(string $url): string
    {
        $path = parse_url(trim($url), PHP_URL_PATH) ?: '/';

        return rtrim(mb_strtolower($path), '/') ?: '/';
    }

    private function deviceName(string $d): string
    {
        return ['MOBILE' => 'телефон', 'DESKTOP' => 'комп’ютер', 'TABLET' => 'планшет'][strtoupper($d)] ?? $d;
    }

    private function m(array $r): array
    {
        return ['impressions' => (int) $r['impressions'], 'clicks' => (int) $r['clicks'], 'ctr' => round((float) $r['ctr'], 4), 'position' => round((float) $r['position'], 1)];
    }

    private function empty(): array
    {
        $z = ['impressions' => 0, 'clicks' => 0, 'ctr' => 0.0, 'position' => null];

        return ['period' => [], 'total' => $z, 'prev' => $z, 'pages' => [], 'days' => [], 'devices' => [], 'countries' => [], 'related' => []];
    }
}
