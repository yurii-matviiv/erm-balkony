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
    public const DAILY_MAX_DAYS = 31;

    public const WEEKLY_MAX_DAYS = 140;

    /** Fewer points than this make a correlation meaningless. */
    public const MIN_POINTS = 5;

    /**
     * @return array{step:string,stepLabel:string,points:list<array<string,mixed>>,correlation:?float,maxLeads:int,maxRate:float,maxTurnover:float}
     */
    public function forManager(int $managerId, string $from, string $to): array
    {
        return Cache::remember(
            "manager-conversion:trend:v1:{$managerId}:{$from}:{$to}",
            now()->addMinutes(ManagerStatsService::CACHE_MINUTES),
            fn () => $this->build($managerId, $from, $to),
        );
    }

    private function build(int $managerId, string $from, string $to): array
    {
        $start = $from.' 00:00:00';
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

        $days = Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1;
        [$step, $stepLabel] = match (true) {
            $days <= self::DAILY_MAX_DAYS => ['day', 'по днях'],
            $days <= self::WEEKLY_MAX_DAYS => ['week', 'по тижнях'],
            default => ['month', 'по місяцях'],
        };

        $buckets = [];
        for ($d = Carbon::parse($from); $d->toDateString() <= $to; $d->addDay()) {
            $key = match ($step) {
                'day' => $d->toDateString(),
                'week' => $d->copy()->startOfWeek()->toDateString(),
                default => $d->copy()->startOfMonth()->toDateString(),
            };
            $row = $leads->get($d->toDateString());
            $buckets[$key] ??= ['start' => max($key, $from), 'end' => $d->toDateString(), 'leads' => 0, 'converted' => 0, 'turnover' => 0.0];
            $buckets[$key]['end'] = $d->toDateString();
            $buckets[$key]['leads'] += (int) ($row->leads ?? 0);
            $buckets[$key]['converted'] += (int) ($row->converted ?? 0);
            $buckets[$key]['turnover'] += (float) ($orders->get($d->toDateString())->turnover ?? 0);
        }

        $points = array_values(array_map(function (array $b) use ($step): array {
            $b['rate'] = $b['leads'] > 0 ? round($b['converted'] / $b['leads'] * 100, 1) : null;
            $b['label'] = match ($step) {
                'day' => Carbon::parse($b['start'])->format('d.m'),
                'week' => Carbon::parse($b['start'])->format('d.m').'–'.Carbon::parse($b['end'])->format('d.m'),
                default => Carbon::parse($b['start'])->translatedFormat('M Y'),
            };

            return $b;
        }, $buckets));

        return [
            'step' => $step,
            'stepLabel' => $stepLabel,
            'points' => $points,
            'correlation' => $this->correlation($points),
            'maxLeads' => (int) max(1, ...array_column($points, 'leads')),
            'maxRate' => (float) max(1, ...array_map(fn ($p) => $p['rate'] ?? 0, $points)),
            'maxTurnover' => (float) max(1, ...array_column($points, 'turnover')),
        ];
    }

    /** Pearson correlation between leads and conversion % over points that have leads. */
    private function correlation(array $points): ?float
    {
        $pairs = array_values(array_filter($points, fn ($p) => $p['leads'] > 0 && $p['rate'] !== null));
        $n = count($pairs);
        if ($n < self::MIN_POINTS) {
            return null;
        }

        $x = array_column($pairs, 'leads');
        $y = array_column($pairs, 'rate');
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
