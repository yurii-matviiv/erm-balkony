<?php

namespace App\Services\Seo;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Збирає знімок позицій із Search Console й пише в seo_position_snapshots (ідемпотентно по даті).
 * Склад топ-30 фіксується першим знімком (seo_tracked_items) і далі не змінюється, доки не викликано refreshTop.
 */
class SeoSnapshotService
{
    public function __construct(private SearchConsoleService $sc) {}

    /** @return array{start: string, end: string} */
    public function period(): array
    {
        $end = Carbon::today()->subDays((int) config('seo.lag_days'));

        return [
            'start' => $end->copy()->subDays((int) config('seo.window_days') - 1)->toDateString(),
            'end' => $end->toDateString(),
        ];
    }

    public static function norm(string $url): string
    {
        return rtrim(strtolower($url), '/');
    }

    /** @return list<array<string,mixed>> рядки для запису (нічого не пише в БД) */
    public function collect(bool $refreshTop = false, bool $persist = true): array
    {
        ['start' => $s, 'end' => $e] = $this->period();
        $rows = [];

        // ── Сторінки за період: основа і для нових сторінок, і для топ-сторінок
        $pages = $this->sc->query($s, $e, ['page'], 5000);
        $byPage = [];
        foreach ($pages as $r) {
            $byPage[self::norm($r['keys'][0])] = ['url' => $r['keys'][0]] + $this->metrics($r);
        }

        // 1) Нові сторінки (рядок і без даних)
        $base = rtrim(config('seo.site_url'), '/');
        foreach (config('seo.new_pages') as $path) {
            $hit = $byPage[self::norm($base.$path)] ?? null;
            $url = $hit['url'] ?? $base.$path;
            $rows[] = $this->row('new_page', $url, '', $hit ?? $this->zero());

            if ($hit) {
                $limit = (int) config('seo.new_page_queries_limit');
                $qs = $this->sc->query($s, $e, ['query'], $limit, [
                    ['dimension' => 'page', 'operator' => 'equals', 'expression' => $url],
                ]);
                foreach ($qs as $q) {
                    $rows[] = $this->row('new_page_query', $url, $q['keys'][0], $this->metrics($q));
                }
            }
        }

        // 2) Топ-30 сторінок
        $tracked = $this->trackedSet('top_page', $refreshTop, $persist, fn () => $this->topKeys($pages, 'page'));
        foreach ($tracked as $item) {
            $m = $byPage[self::norm($item)] ?? null;
            $rows[] = $this->row('top_page', $m['url'] ?? $item, '', $m ?? $this->zero());
        }

        // 3) Топ-30 запитів
        $queries = $this->sc->query($s, $e, ['query'], 5000);
        $byQuery = [];
        foreach ($queries as $r) {
            $byQuery[$r['keys'][0]] = $this->metrics($r);
        }
        $trackedQ = $this->trackedSet('top_query', $refreshTop, $persist, fn () => $this->topKeys($queries, 'query'));
        foreach ($trackedQ as $item) {
            $rows[] = $this->row('top_query', '', $item, $byQuery[$item] ?? $this->zero());
        }

        return $rows;
    }

    /** @param list<array<string,mixed>> $rows */
    public function store(array $rows): int
    {
        ['start' => $s, 'end' => $e] = $this->period();
        $today = Carbon::today()->toDateString();

        foreach ($rows as $r) {
            DB::table('seo_position_snapshots')->updateOrInsert(
                ['snapshot_date' => $today, 'scope' => $r['scope'], 'page_hash' => $r['page_hash'], 'query_hash' => $r['query_hash']],
                $r + ['snapshot_date' => $today, 'period_start' => $s, 'period_end' => $e, 'created_at' => now()],
            );
        }

        return count($rows);
    }

    /** Індексація кількох адрес (по 4–5 за виклик через ліміт часу інструмента). */
    public function storeIndexStatus(int $offset = 0, int $limit = 5): array
    {
        $base = rtrim(config('seo.site_url'), '/');
        $paths = array_slice(config('seo.new_pages'), $offset, $limit);
        $today = Carbon::today()->toDateString();
        $out = [];

        foreach ($paths as $path) {
            $url = $base.$path;
            try {
                $st = $this->sc->inspect($url);
            } catch (\Throwable $ex) {
                $st = ['coverage_state' => 'помилка: '.$ex->getMessage(), 'last_crawl_time' => null, 'google_canonical' => null];
            }
            DB::table('seo_index_status')->updateOrInsert(
                ['snapshot_date' => $today, 'page_hash' => sha1(self::norm($url))],
                ['page' => $url, 'created_at' => now()] + $st,
            );
            $out[$url] = $st['coverage_state'];
        }

        return $out;
    }

    /** @return list<string> */
    private function trackedSet(string $scope, bool $refresh, bool $persist, \Closure $pick): array
    {
        $has = DB::table('seo_tracked_items')->where('scope', $scope)->whereNull('removed_at')->exists();

        // --dry: нічого не записуємо; якщо набір ще не зафіксовано — показуємо, що було б вибрано.
        if (! $persist) {
            return ($has && ! $refresh)
                ? DB::table('seo_tracked_items')->where('scope', $scope)->whereNull('removed_at')->orderBy('id')->pluck('item')->all()
                : $pick();
        }

        if (! $has || $refresh) {
            $today = Carbon::today()->toDateString();
            DB::table('seo_tracked_items')->where('scope', $scope)->whereNull('removed_at')->update(['removed_at' => $today]);
            foreach ($pick() as $item) {
                DB::table('seo_tracked_items')->updateOrInsert(
                    ['scope' => $scope, 'item_hash' => sha1(self::norm($item))],
                    ['item' => $item, 'added_at' => $today, 'removed_at' => null, 'created_at' => now(), 'updated_at' => now()],
                );
            }
        }

        return DB::table('seo_tracked_items')->where('scope', $scope)->whereNull('removed_at')->orderBy('id')->pluck('item')->all();
    }

    /** Топ за кліками, за рівності — за показами. */
    private function topKeys(array $apiRows, string $kind): array
    {
        usort($apiRows, fn ($a, $b) => [$b['clicks'], $b['impressions']] <=> [$a['clicks'], $a['impressions']]);

        return array_map(fn ($r) => $r['keys'][0], array_slice($apiRows, 0, (int) config('seo.top_limit')));
    }

    private function metrics(array $r): array
    {
        return [
            'impressions' => (int) $r['impressions'],
            'clicks' => (int) $r['clicks'],
            'ctr' => round((float) $r['ctr'], 6),
            'position' => round((float) $r['position'], 2),
        ];
    }

    private function zero(): array
    {
        return ['impressions' => 0, 'clicks' => 0, 'ctr' => 0, 'position' => null];
    }

    private function row(string $scope, string $page, string $query, array $m): array
    {
        return [
            'scope' => $scope,
            'page' => $page,
            'query' => $query,
            'page_hash' => sha1(self::norm($page)),
            'query_hash' => sha1(mb_strtolower($query)),
            'impressions' => $m['impressions'],
            'clicks' => $m['clicks'],
            'ctr' => $m['ctr'],
            'position' => $m['position'],
        ];
    }
}
