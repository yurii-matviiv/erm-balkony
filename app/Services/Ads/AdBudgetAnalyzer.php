<?php

namespace App\Services\Ads;

use Illuminate\Support\Facades\DB;

/**
 * Where to move the ad budget: compares each Google Ads campaign's SHARE of spend with its
 * share of turnover and suggests a target share (all in percent, totals are not needed).
 *
 * Definitions (kept in one place so the numbers stay explainable):
 *  - Window: WINDOW_DAYS days ending SKIP_RECENT_DAYS ago — orders appear weeks after a lead,
 *    so the freshest weeks would look unfairly bad.
 *  - Витрати: Google cost of the campaign in the window (ad_campaign_daily).
 *  - Оборот: SUM(orders.total_price) (non-cancelled) of the clients who left a Google/cpc lead
 *    in the window with a utm_campaign mapped to the campaign. A client with leads in two
 *    campaigns is credited to both (rare). The user's "прибуток" = оборот.
 *  - Віддача = оборот / витрати.
 *  - Скоригована віддача: pulled toward the account average by PRIOR_SPEND of "virtual" spend
 *    (guards against one lucky big order on a small campaign).
 *  - Рекомендована частка ∝ скоригована віддача, but never above what the campaign can absorb:
 *    cap = current spend / (1 - search budget-lost impression share), at most MAX_SCALE x, known only for Search.
 *  - Campaigns with too few orders (MIN_ORDERS) keep their current share: no move on a guess.
 */
class AdBudgetAnalyzer
{
    public const WINDOW_DAYS = 90;

    public const SKIP_RECENT_DAYS = 14;

    /** UAH of virtual spend at the account-average return. */
    public const PRIOR_SPEND = 8000;

    /** A budget increase is never assumed to scale beyond this multiple of current spend. */
    public const MAX_SCALE = 2.0;

    /** Share change (percentage points) below which we say "keep". */
    public const KEEP_BAND = 5;

    /** Fewer orders than this = low confidence. */
    public const MIN_ORDERS = 3;

    /**
     * @return array<string, mixed>
     */
    public function analyze(): array
    {
        $to = now()->subDays(self::SKIP_RECENT_DAYS)->toDateString();
        $from = now()->subDays(self::SKIP_RECENT_DAYS + self::WINDOW_DAYS - 1)->toDateString();

        $spend = DB::table('ad_campaign_daily')
            ->whereBetween('date', [$from, $to])
            ->groupBy('google_campaign_id')
            ->selectRaw('google_campaign_id g, SUM(cost_micros)/1000000 spend')
            ->pluck('spend', 'g');

        $campaigns = DB::table('ad_campaigns')->get()->keyBy('google_campaign_id');

        $lastSpend = DB::table('ad_campaign_daily')->where('cost_micros', '>', 0)
            ->groupBy('google_campaign_id')->selectRaw('google_campaign_id g, MAX(date) d')->pluck('d', 'g');

        $headroom = DB::table('ad_campaigns')->whereNotNull('budget_lost_35d')->pluck('budget_lost_35d', 'google_campaign_id');

        $pairs = DB::table('lead_marketing_data as lm')
            ->join('leads as l', 'l.id', '=', 'lm.lead_id')
            ->join('ad_campaign_mappings as m', 'm.utm_campaign', '=', 'lm.utm_campaign')
            ->where('lm.utm_source', 'google')->where('lm.utm_medium', 'cpc')
            ->whereBetween('l.created_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->whereNotNull('l.client_id')
            ->select('m.google_campaign_id', 'l.client_id')->distinct();

        $revenue = DB::query()->fromSub($pairs, 'p')
            ->leftJoin('orders as o', function ($j) {
                $j->on('o.client_id', '=', 'p.client_id')->where('o.status', '<>', 'cancelled')->where('o.total_price', '>', 0);
            })
            ->groupBy('p.google_campaign_id')
            ->selectRaw('p.google_campaign_id g, COUNT(DISTINCT p.client_id) clients, COUNT(DISTINCT o.id) orders, COALESCE(SUM(o.total_price),0) revenue')
            ->get()->keyBy('g');

        $unconfirmed = DB::table('ad_campaign_mappings')->where('confirmed', false)->pluck('google_campaign_id')->flip();

        $totalSpend = (float) $spend->sum();
        $totalRevenue = (float) $revenue->sum('revenue');
        $avgRoas = $totalSpend > 0 ? $totalRevenue / $totalSpend : 0.0;

        $rows = [];

        foreach ($spend as $gid => $s) {
            $s = (float) $s;

            if ($s <= 0) {
                continue;
            }

            $campaign = $campaigns->get($gid);
            $rev = (float) ($revenue->get($gid)->revenue ?? 0);
            $orders = (int) ($revenue->get($gid)->orders ?? 0);
            $leadClients = (int) ($revenue->get($gid)->clients ?? 0);
            $adj = ($rev + self::PRIOR_SPEND * $avgRoas) / ($s + self::PRIOR_SPEND);
            $lost = isset($headroom[$gid]) ? min(0.9, max(0.0, (float) $headroom[$gid])) : null;

            $rows[$gid] = [
                'id' => $gid,
                'name' => $campaign->name ?? $gid,
                'status' => $campaign->status ?? null,
                'channel' => $campaign->channel_type ?? null,
                'last_spend' => $lastSpend[$gid] ?? null,
                'spend' => $s,
                'spend_share' => $totalSpend > 0 ? $s / $totalSpend * 100 : 0,
                'revenue' => $rev,
                'revenue_share' => $totalRevenue > 0 ? $rev / $totalRevenue * 100 : 0,
                'orders' => $orders,
                'clients' => $leadClients,
                'roas' => $rev / $s,
                'adj_roas' => $adj,
                'headroom_lost' => $lost,
                'cap_share' => $lost !== null && $totalSpend > 0 && ($campaign->channel_type ?? null) === 'SEARCH'
                    ? $s * min(self::MAX_SCALE, 1 / (1 - $lost)) / $totalSpend * 100
                    : null,
                'mapped_unconfirmed' => isset($unconfirmed[$gid]),
                'low_confidence' => $orders < self::MIN_ORDERS,
            ];
        }

        $this->allocate($rows);

        foreach ($rows as &$r) {
            $r['delta'] = $r['rec_share'] - $r['spend_share'];
            $r['action'] = $r['delta'] >= self::KEEP_BAND ? 'up' : ($r['delta'] <= -self::KEEP_BAND ? 'down' : 'hold');

        }
        unset($r);

        usort($rows, fn ($a, $b) => $b['spend_share'] <=> $a['spend_share']);

        $unmapped = DB::table('lead_marketing_data as lm')
            ->join('leads as l', 'l.id', '=', 'lm.lead_id')
            ->leftJoin('ad_campaign_mappings as m', 'm.utm_campaign', '=', 'lm.utm_campaign')
            ->where('lm.utm_source', 'google')->where('lm.utm_medium', 'cpc')
            ->whereNull('m.id')->whereNotNull('lm.utm_campaign')->where('lm.utm_campaign', '<>', '')
            ->whereBetween('l.created_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->groupBy('lm.utm_campaign')->selectRaw('lm.utm_campaign utm, COUNT(*) leads')
            ->orderByDesc('leads')->get();

        return [
            'from' => $from,
            'to' => $to,
            'rows' => array_values($rows),
            'avg_roas' => $avgRoas,
            'unmapped' => $unmapped,
            'mappings' => DB::table('ad_campaign_mappings')->orderBy('utm_campaign')->get(),
            'campaigns' => $campaigns,
            'synced_at' => DB::table('ad_campaigns')->max('updated_at'),
        ];
    }

    /**
     * Shares proportional to the adjusted return, capped by what each campaign can absorb.
     *
     * @param  array<string, array<string, mixed>>  $rows
     */
    private function allocate(array &$rows): void
    {
        $remaining = 100.0;
        $active = [];

        foreach ($rows as $k => &$r) {
            if ($r['low_confidence']) {
                // too few orders to judge: keep as is, do not move money in or out on a guess
                $r['rec_share'] = $r['spend_share'];
                $remaining -= $r['spend_share'];
            } else {
                $r['rec_share'] = 0.0;
                $active[] = $k;
            }
        }
        unset($r);

        while ($active !== [] && $remaining > 0.001) {
            $sum = array_sum(array_map(fn ($k) => max(0.0001, $rows[$k]['adj_roas']), $active));
            $capped = [];

            foreach ($active as $k) {
                $share = $remaining * max(0.0001, $rows[$k]['adj_roas']) / $sum;
                $cap = $rows[$k]['cap_share'];

                if ($cap !== null && $share > $cap) {
                    $capped[$k] = $cap;
                }
            }

            if ($capped === []) {
                foreach ($active as $k) {
                    $rows[$k]['rec_share'] = $remaining * max(0.0001, $rows[$k]['adj_roas']) / $sum;
                }

                return;
            }

            foreach ($capped as $k => $cap) {
                $rows[$k]['rec_share'] = $cap;
                $remaining -= $cap;
            }

            $active = array_values(array_diff($active, array_keys($capped)));
        }
    }
}
