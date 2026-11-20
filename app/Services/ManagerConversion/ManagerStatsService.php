<?php

namespace App\Services\ManagerConversion;

use App\Models\Expense;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Per-period numbers of the «Конверсія менеджерів» page: one row per active manager
 * (leads, conversion, turnover, ad cost, load) plus company ad totals.
 *
 * "Active" = the manager has at least one lead or order in the period.
 *
 * Definitions (kept in one place so the numbers stay explainable):
 *  - Заявки: leads created in the period, assigned to the manager.
 *  - Замовлення (конверсія): those same leads that have at least one non-cancelled order
 *    (cohort-based on purpose — conversion can never exceed 100%).
 *  - Оборот: SUM(orders.total_price) of non-cancelled orders created in the period by the manager.
 *  - Реклама: all marketing expenses of the period. Average ad cost per lead = that sum / ALL leads of
 *    the period; a manager is charged that average per lead. Allowed ad budget = AD_BUDGET_SHARE of his
 *    turnover; result = allowed budget - charged cost; ДРР = charged cost / turnover.
 *    Approximation by design (ad payments are bank top-ups, non-ad leads share the same average).
 */
final class ManagerStatsService
{
    /**
     * Typical order margin (turnover minus supplier/installer/other order
     * payments), measured on MATURE orders (Sep 2025 – Jul 2026: 17–19% per
     * month). Recent months can't be measured from payments — their costs
     * are not paid yet, so margin looks inflated (Oct 2026: 68%).
     */
    public const ORDER_MARGIN = 0.18;

    /** Manager's salary: 7% of turnover (business rule). */
    public const MANAGER_SALARY = 0.07;

    /** Comfortable workload: leads one manager handles per working day (business rule: ~7). */
    public const LEADS_PER_DAY_NORM = 7;

    /** Load = leads per working day / norm. Below LOW there is room for more; above FULL he is at the limit. */
    public const LOAD_LOW = 0.5;
    public const LOAD_FULL = 0.9;
    public const LOAD_OVER = 1.1;

    /** Page numbers are recalculated at most this often (minutes). */
    public const CACHE_MINUTES = 5;

    /** What is left for advertising before a manager goes into the red: 18% - 7% = 11% of turnover. */
    public const AD_BUDGET_SHARE = self::ORDER_MARGIN - self::MANAGER_SALARY;


    /**
     * Heavy DB numbers for one period, cached for CACHE_MINUTES: opening or refreshing the page does not recompute them.
     *
     * @return array{adSpend:float,totalLeads:int,costPerLead:float,managers:list<array<string,mixed>>,computedAt:int}
     */
    public function forPeriod(string $from, string $to): array
    {
        return Cache::remember($this->cacheKey($from, $to), now()->addMinutes(self::CACHE_MINUTES), function () use ($from, $to): array {
            $adSpend = (float) Expense::query()
                ->where('direction', 'outgo')
                ->where('status', 'received')
                ->where('category', 'marketing')
                ->whereBetween('paid_at', [$from.' 00:00:00', $to.' 23:59:59'])
                ->sum('amount');

            $totalLeads = (int) DB::table('leads')
                ->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])
                ->count();

            $costPerLead = $totalLeads > 0 ? $adSpend / $totalLeads : 0.0;

            return [
                'adSpend' => $adSpend,
                'totalLeads' => $totalLeads,
                'costPerLead' => $costPerLead,
                'managers' => $this->managers($from, $to, $costPerLead),
                'computedAt' => now()->timestamp,
            ];
        });
    }

    public function cacheKey(string $from, string $to): string
    {
        return "manager-conversion:base:{$from}:{$to}";
    }

    /**
     * Mon–Fri days in the period, but never beyond today (future days carry no leads yet).
     * Leads almost never arrive on weekends (Sat+Sun ≈ 4% in the data), so calendar days would understate the load.
     */
    public function workingDays(string $from, string $to): int
    {
        $end = min($to, now()->toDateString());
        $n = 0;

        for ($d = \Carbon\Carbon::parse($from); $d->toDateString() <= $end; $d->addDay()) {
            if ($d->isWeekday()) {
                $n++;
            }
        }

        return $n;
    }

    /**
     * @return list<array{id:int,name:string,leads:int,converted:int,orders:int,rate:float,turnover:float,ad_cost:float,ad_budget:float,drr:?float,result:float,per_day:?float,load:?float,load_level:string,peak:int}>
     */
    public function managers(string $from, string $to, float $costPerLead = 0.0): array
    {
        $start = $from.' 00:00:00';
        $end = $to.' 23:59:59';

        $leads = DB::table('leads')
            ->whereNotNull('manager_id')
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('manager_id, COUNT(*) AS leads, SUM(EXISTS (
                SELECT 1 FROM orders o WHERE o.lead_id = leads.id AND o.status <> ?
            )) AS converted', ['cancelled'])
            ->groupBy('manager_id')
            ->get()
            ->keyBy('manager_id');

        $peaks = DB::table('leads')
            ->whereNotNull('manager_id')
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('manager_id, DATE(created_at) AS d, COUNT(*) AS c')
            ->groupBy('manager_id', 'd')
            ->get()
            ->groupBy('manager_id')
            ->map(fn ($rows) => (int) $rows->max('c'));

        $workDays = $this->workingDays($from, $to);

        $orders = DB::table('orders')
            ->whereNotNull('manager_id')
            ->where('status', '<>', 'cancelled')
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('manager_id, COUNT(*) AS orders, COALESCE(SUM(total_price), 0) AS turnover')
            ->groupBy('manager_id')
            ->get()
            ->keyBy('manager_id');

        $ids = $leads->keys()->merge($orders->keys())->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $users = DB::table('users')->whereIn('id', $ids)->get(['id', 'name', 'last_name'])->keyBy('id');

        $rows = [];
        foreach ($ids as $id) {
            $l = (int) ($leads[$id]->leads ?? 0);
            $c = (int) ($leads[$id]->converted ?? 0);
            $u = $users[$id] ?? null;

            $perDay = $workDays > 0 ? $l / $workDays : null;
            $load = $perDay !== null ? $perDay / self::LEADS_PER_DAY_NORM : null;

            $rows[] = [
                'per_day' => $perDay !== null ? round($perDay, 1) : null,
                'load' => $load !== null ? round($load * 100) : null,
                'load_level' => match (true) {
                    $load === null => 'none',
                    $load < self::LOAD_LOW => 'low',
                    $load < self::LOAD_FULL => 'ok',
                    $load < self::LOAD_OVER => 'full',
                    default => 'over',
                },
                'peak' => (int) ($peaks[$id] ?? 0),
                'id' => (int) $id,
                'name' => $u ? trim(($u->name ?? '').' '.($u->last_name ?? '')) : "#{$id}",
                'leads' => $l,
                'converted' => $c,
                'orders' => (int) ($orders[$id]->orders ?? 0),
                'rate' => $l > 0 ? round($c / $l * 100, 1) : 0.0,
                'turnover' => (float) ($orders[$id]->turnover ?? 0),
                'ad_cost' => round($l * $costPerLead, 2),
                'ad_budget' => round((float) ($orders[$id]->turnover ?? 0) * self::AD_BUDGET_SHARE, 2),
                'drr' => (float) ($orders[$id]->turnover ?? 0) > 0 ? round($l * $costPerLead / (float) $orders[$id]->turnover * 100, 1) : null,
                'result' => round((float) ($orders[$id]->turnover ?? 0) * self::AD_BUDGET_SHARE - $l * $costPerLead, 2),
            ];
        }

        usort($rows, fn ($a, $b) => $b['turnover'] <=> $a['turnover'] ?: $b['leads'] <=> $a['leads']);

        return $rows;
    }
}
