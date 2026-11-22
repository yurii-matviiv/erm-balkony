<?php

namespace App\Filament\Widgets;

use App\Services\Seo\SeoKeywordService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

/** Ключові цифри слова за період порівняно з попереднім (стандартний віджет Filament). Лише для сторінки «Ключове слово». */
class SeoKeywordStats extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $pollingInterval = null;

    public string $keyword = '';

    protected function getHeading(): ?string
    {
        $p = app(SeoKeywordService::class)->live($this->keyword)['period'] ?? [];

        return $p ? 'Період '.Carbon::parse($p['start'])->format('d.m').'–'.Carbon::parse($p['end'])->format('d.m.Y').', порівняння з попередніми днями '.Carbon::parse($p['prev_start'])->format('d.m').'–'.Carbon::parse($p['prev_end'])->format('d.m') : null;
    }

    protected function getStats(): array
    {
        $d = app(SeoKeywordService::class)->live($this->keyword);
        $t = $d['total'];
        $p = $d['prev'];

        $diff = function (float|int|null $now, float|int|null $was, bool $lowerBetter = false, int $dec = 0, string $unit = ''): array {
            if ($now === null || $was === null) {
                return ['—', 'gray', null];
            }
            $delta = round($now - $was, $dec);
            if ($delta == 0) {
                return ['без змін', 'gray', null];
            }
            $good = $lowerBetter ? $delta < 0 : $delta > 0;

            return [($delta > 0 ? '+' : '').number_format($delta, $dec, ',', ' ').$unit.' до попереднього', $good ? 'success' : 'danger', $delta > 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down'];
        };

        [$di, $ci, $ii] = $diff($t['impressions'], $p['impressions']);
        [$dc, $cc, $ic] = $diff($t['clicks'], $p['clicks']);
        [$dp, $cp, $ip] = $diff($t['position'], $p['position'], true, 1);

        $stat = fn (string $label, string $value, ?string $desc = null, string $color = 'gray', ?string $icon = null): Stat => Stat::make($label, $value)
            ->description($desc)->color($color)->descriptionIcon($icon);

        return [
            $stat('Покази', number_format($t['impressions'], 0, ',', ' '), $di, $ci, $ii),
            $stat('Кліки', number_format($t['clicks'], 0, ',', ' '), $dc, $cc, $ic),
            $stat('CTR', number_format($t['ctr'] * 100, 1, ',', '').'%', $t['impressions'] < config('seo.min_impressions') ? 'мало показів — орієнтовно' : null),
            $stat('Середня позиція', $t['position'] === null ? '—' : number_format($t['position'], 1, ',', ''), $t['impressions'] < config('seo.min_impressions') ? 'мало показів — орієнтовно' : $dp, $t['impressions'] < config('seo.min_impressions') ? 'warning' : $cp, $ip),
            $stat('Сторінок у видачі', (string) count($d['pages'])),
        ];
    }
}
