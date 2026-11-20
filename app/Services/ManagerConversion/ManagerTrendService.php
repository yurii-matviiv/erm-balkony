<?php

namespace App\Services\ManagerConversion;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Dynamics of ONE manager over the chosen period: leads, conversion and turnover per day / week / month,
 * plus the correlation between the number of leads and conversion.
 *
 * Same definitions as the cards: a lead counts on the day it was created and is "converted" if it has a
 * non-cancelled order (cohort-based); turnover = non-cancelled orders created that day by the manager.
 */
final class ManagerTrendService
{
    /** Fewer points than this make a correlation meaningless. */
    public const MIN_POINTS = 5;

    /**
     * Rolling window (days) behind every point of the lines: single days jump around (weekends, empty days),
     * a window shows the actual trend. Longer periods get a longer window.
     */
    public static function windowDays(int $periodDays): int
    {
        return match (true) {
            $periodDays <= 60 => 7,
            $periodDays <= 180 => 14,
            default => 30,
        };
    }

    /**
     * @return array{window:int,points:list<array<string,mixed>>,correlation:?float,corrPoints:int,min:array<string,float>,max:array<string,float>}
     */
    public function forManager(int $managerId, string $from, string $to): array
    {
        return Cache::remember(
            "manager-conversion:trend:v2:{$managerId}:{$from}:{$to}",
            now()->addMinutes(ManagerStatsService::CACHE_MINUTES),
            fn () => $this->build($managerId, $from, $to),
        );
    }

    private function build(int $managerId, string $from, string $to): array
    {
        // Days in the future carry nothing yet: drawing them would be a flat line at zero.
        $to = min($to, now()->toDateString());
        $periodDays = Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1;
        $window = self::windowDays($periodDays);

        // Load extra days before the start so the first point already has a full window.
        $loadFrom = Carbon::parse($from)->subDays($window - 1)->toDateString();
        $start = $loadFrom.' 00:00:00';
        $end = $to.' 23:59:59';

        $leads = DB::table('leads')
            ->where('manager_id', $managerId)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('DATE(created_at) AS d, COUNT(*) AS leads, SUM(EXISTS (
                SELECT 1 FROM orders o WHERE o.lead_id = leads.id AND o.status <> ?
            )) AS converted', ['cancelled'])
            ->groupBy('d')
            ->get()
            ->keyBy('d');

        $orders = DB::table('orders')
            ->where('manager_id', $managerId)
            ->where('status', '<>', 'cancelled')
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('DATE(created_at) AS d, COALESCE(SUM(total_price), 0) AS turnover')
            ->groupBy('d')
            ->get()
            ->keyBy('d');

        $daily = [];
        for ($d = Carbon::parse($loadFrom); $d->toDateString() <= $to; $d->addDay()) {
            $k = $d->toDateString();
            $daily[] = [
                'date' => $k,
                'leads' => (int) ($leads->get($k)->leads ?? 0),
                'converted' => (int) ($leads->get($k)->converted ?? 0),
                'turnover' => (float) ($orders->get($k)->turnover ?? 0),
            ];
        }

        $points = [];
        for ($i = $window - 1; $i < count($daily); $i++) {
            $slice = array_slice($daily, $i - $window + 1, $window);
            $l = array_sum(array_column($slice, 'leads'));
            $c = array_sum(array_column($slice, 'converted'));
            $points[] = [
                'date' => $daily[$i]['date'],
                'label' => Carbon::parse($daily[$i]['date'])->format('d.m'),
                'from' => $slice[0]['date'],
                'leads' => $l,
                'converted' => $c,
                // conversion of the whole window (sum / sum), not an average of daily percentages
                'rate' => $l > 0 ? round($c / $l * 100, 1) : null,
                'turnover' => array_sum(array_column($slice, 'turnover')),
            ];
        }

        $series = ['leads', 'rate', 'turnover'];
        $min = $max = [];
        foreach ($series as $k) {
            $vals = array_values(array_filter(array_column($points, $k), fn ($v) => $v !== null));
            $min[$k] = $vals ? (float) min($vals) : 0.0;
            $max[$k] = $vals ? (float) max($vals) : 0.0;
        }

        // Correlation on real single days (only those with leads), without the smoothing: smoothing would inflate it.
        $inPeriod = array_values(array_filter($daily, fn ($d) => $d['date'] >= $from));

        return [
            'window' => $window,
            'points' => $points,
            'correlation' => $this->correlation($inPeriod),
            'corrPoints' => count(array_filter($inPeriod, fn ($d) => $d['leads'] > 0)),
            'min' => $min,
            'max' => $max,
        ];
    }

    /** Pearson correlation between leads per day and that day's conversion %, over days that have leads. */
    private function correlation(array $days): ?float
    {
        $pairs = array_values(array_filter($days, fn ($d) => $d['leads'] > 0));
        $n = count($pairs);
        if ($n < self::MIN_POINTS) {
            return null;
        }

        $x = array_column($pairs, 'leads');
        $y = array_map(fn ($d) => $d['converted'] / $d['leads'] * 100, $pairs);
        $mx = array_sum($x) / $n;
        $my = array_sum($y) / $n;
        $sxy = $sxx = $syy = 0.0;
        foreach ($pairs as $i => $_) {
            $sxy += ($x[$i] - $mx) * ($y[$i] - $my);
            $sxx += ($x[$i] - $mx) ** 2;
            $syy += ($y[$i] - $my) ** 2;
        }

        return $sxx > 0 && $syy > 0 ? round($sxy / sqrt($sxx * $syy), 2) : null;
    }
}
