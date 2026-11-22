<?php

namespace App\Services\Seo;

use Illuminate\Support\Facades\DB;

/** Порівняння останнього знімка з попереднім: що змінилось (пороги — config/seo.php). */
class SeoReportService
{
    /** @return array{current: ?string, previous: ?string, events: list<array<string,mixed>>} */
    public function changes(): array
    {
        $dates = DB::table('seo_position_snapshots')->distinct()->orderByDesc('snapshot_date')->limit(2)->pluck('snapshot_date')->all();
        if (count($dates) < 2) {
            return ['current' => $dates[0] ?? null, 'previous' => null, 'events' => []];
        }
        [$cur, $prev] = $dates;

        $load = fn (string $d) => DB::table('seo_position_snapshots')->where('snapshot_date', $d)
            ->whereIn('scope', ['new_page', 'top_page', 'top_query'])->get()
            ->keyBy(fn ($r) => $r->scope.'|'.$r->page_hash.'|'.$r->query_hash);

        $a = $load($cur);
        $b = $load($prev);
        $posT = (float) config('seo.position_change');
        $impT = (float) config('seo.impressions_change_pct');
        $min = (int) config('seo.min_impressions');
        $events = [];

        foreach ($a as $key => $now) {
            $was = $b[$key] ?? null;
            if (! $was) {
                continue;
            }
            $label = $now->scope === 'top_query' ? $now->query : $now->page;

            if ($now->scope === 'new_page' && $was->impressions == 0 && $now->impressions > 0) {
                $events[] = $this->ev('appeared', $now, $label, 'Сторінка з’явилась у видачі: '.$now->impressions.' показів, позиція '.$now->position);
                continue;
            }
            if ($now->scope === 'top_query' && $was->impressions > 0 && $now->impressions == 0) {
                $events[] = $this->ev('lost_query', $now, $label, 'Запит випав з видачі (було '.$was->impressions.' показів)');
                continue;
            }
            if ($now->impressions < $min || $was->impressions < $min) {
                continue; // замало показів — висновків не робимо
            }
            $dPos = (float) $now->position - (float) $was->position;
            if (abs($dPos) >= $posT) {
                $events[] = $this->ev('position', $now, $label, sprintf('Позиція %s → %s (%s%.1f)', $was->position, $now->position, $dPos < 0 ? '▲ ' : '▼ +', $dPos < 0 ? abs($dPos) : $dPos));
            }
            $pct = ($now->impressions - $was->impressions) / $was->impressions * 100;
            if (abs($pct) >= $impT) {
                $events[] = $this->ev('impressions', $now, $label, sprintf('Покази %d → %d (%+.0f%%)', $was->impressions, $now->impressions, $pct));
            }
        }

        return ['current' => $cur, 'previous' => $prev, 'events' => $events];
    }

    private function ev(string $type, object $row, string $label, string $text): array
    {
        return ['type' => $type, 'scope' => $row->scope, 'label' => $label, 'text' => $text];
    }
}
