<?php

namespace App\Services\ManagerConversion;

use Illuminate\Support\Facades\DB;

/** «Розподіл заявок»: recommended share of each lead source per manager. */
final class RecommendationService
{
    /** Recommendations use a fixed, mature window (independent of the date filter): orders appear weeks after a lead. */
    public const REC_WINDOW_DAYS = 90;
    public const REC_SKIP_RECENT_DAYS = 14;
    /** Fewer leads than this per manager+source = too noisy to judge. */
    public const REC_MIN_LEADS = 30;
    /** Conversion is pulled toward the company average by this many "virtual" leads (guards against 4-of-37 luck). */
    public const REC_PRIOR_LEADS = 30;
    /** Weight = (conversion / average)^POWER: higher = steeper skew toward the strongest. */
    public const REC_POWER = 2;
    /** A manager with too few leads of a source gets half weight until proven. */
    public const REC_UNPROVEN_WEIGHT = 0.5;
    /** Shares are rounded to this step (percent); a share below MIN becomes 0 = do not assign. */
    public const REC_STEP = 5;
    public const REC_MIN_SHARE = 10;
    /** Phone load (answered incoming + outgoing calls per working day, from Binotel). Business view: ~12/day is already a lot. */
    public const PHONE_CALLS_HIGH = 12;
    public const PHONE_CALLS_LOW = 6;
    /** «Зараз» in the recommendation = the actual share of leads over this many latest days (the days left out of the conversion window). */
    public const REC_CURRENT_DAYS = 14;
    /** Difference in share (percentage points) below which the advice reads «без змін». */
    public const REC_NOCHANGE_PP = 3;

    public function __construct(private readonly ManagerStatsService $stats)
    {
    }

    /**
     * "Розподіл заявок" (раніше «Кого на що ставити»): for each lead source (calls / site-ads) the
     * recommended SHARE of that source per active manager (sums to 100%),
     * derived from conversion. The head of sales reads it and routes leads
     * accordingly — the system only advises, nothing is assigned here.
     *
     * Leads are NOT spread randomly between managers (site leads go almost
     * only to one person), so overall conversion is not comparable — the
     * per-source one is. Steps: shrink each conversion toward the company
     * average (small samples can't swing it), weight = (conv/avg)^2, unproven
     * managers get half weight, normalise, round to 5%, drop shares < 10%.
     *
     * Also returns the expected leads/day per manager under the advice, so
     * an overloaded recommendation is visible (norm: LEADS_PER_DAY_NORM).
     *
     * @param  list<array<string,mixed>>  $managers
     * @return array{blocks:list<array<string,mixed>>,expected:list<array{name:string,per_day:float,over:bool}>}
     */
    public function recommend(array $managers, array $phone = [], ?string $from = null, ?string $to = null): array
    {
        if ($managers === []) {
            return ['blocks' => [], 'expected' => []];
        }

        $ids = array_column($managers, 'id');
        $names = array_column($managers, 'name', 'id');
        $from = now()->subDays(self::REC_WINDOW_DAYS);
        $to = now()->subDays(self::REC_SKIP_RECENT_DAYS);

        $rows = DB::table('leads')
            ->whereIn('manager_id', $ids)
            ->whereIn('source', ['call', 'site'])
            ->where('created_at', '>=', $from)
            ->where('created_at', '<', $to)
            ->selectRaw("manager_id, source AS src, COUNT(*) AS n, SUM(EXISTS (
                SELECT 1 FROM orders o WHERE o.lead_id = leads.id AND o.status <> 'cancelled'
            )) AS conv")
            ->groupBy('manager_id', 'source')
            ->get()
            ->groupBy('src');

        // «Зараз»: who actually received the leads over the latest days (not in the conversion window).
        $curRows = DB::table('leads')
            ->whereIn('manager_id', $ids)
            ->whereIn('source', ['call', 'site'])
            ->where('created_at', '>=', now()->subDays(self::REC_CURRENT_DAYS))
            ->selectRaw('manager_id, source AS src, COUNT(*) AS n')
            ->groupBy('manager_id', 'source')
            ->get()
            ->groupBy('src');

        $workDays = max(1, $this->stats->workingDays($from->toDateString(), $to->toDateString()));
        $short = fn (int $id): string => implode(' ', array_slice(explode(' ', trim($names[$id] ?? "#{$id}")), 0, 2));

        $blocks = [];
        $expected = array_fill_keys($ids, 0.0);

        // Phone capacity: current calls/day per manager and his current share of call leads
        // (same period as the Binotel figures) -> how many calls/day 100% of the share would mean for him.
        $phoneByMgr = array_column($phone, null, 'id');
        $callCaps = null;
        if ($phone !== [] && $from && $to) {
            $callLeads = DB::table('leads')->whereIn('manager_id', $ids)->where('source', 'call')
                ->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])
                ->selectRaw('manager_id, COUNT(*) AS n')->groupBy('manager_id')->pluck('n', 'manager_id');
            $callTotal = max(1, (int) $callLeads->sum());
            $companyIntensity = array_sum(array_column($phone, 'calls_per_day')); // calls/day if one person had 100%
            $callCaps = [];
            foreach ($ids as $id) {
                $curShare = ((int) ($callLeads[$id] ?? 0)) / $callTotal;
                $perDay = (float) ($phoneByMgr[$id]['calls_per_day'] ?? 0);
                // Too little own history -> assume the company-average calls per lead.
                $intensity = $curShare >= 0.10 && $perDay > 0 ? $perDay / $curShare : $companyIntensity;
                $callCaps[$id] = $intensity > 0 ? min(1.0, self::PHONE_CALLS_HIGH / $intensity) : 1.0;
            }
        }

        foreach (['call' => 'Дзвінки', 'site' => 'Сайт (реклама)'] as $src => $label) {
            $byManager = ($rows[$src] ?? collect())->keyBy('manager_id');
            $totalN = (int) $byManager->sum('n');
            $avg = $totalN > 0 ? $byManager->sum('conv') / $totalN : 0.0;

            $list = [];
            $weights = [];
            foreach ($ids as $id) {
                $n = (int) ($byManager[$id]->n ?? 0);
                $c = (int) ($byManager[$id]->conv ?? 0);
                $reliable = $n >= self::REC_MIN_LEADS;
                $shrunk = ($c + self::REC_PRIOR_LEADS * $avg) / ($n + self::REC_PRIOR_LEADS);
                $relative = $avg > 0 && $reliable ? $shrunk / $avg : 1.0;

                $weights[$id] = ($relative ** self::REC_POWER) * ($reliable ? 1.0 : self::REC_UNPROVEN_WEIGHT);
                $list[$id] = [
                    'name' => $short((int) $id),
                    'rate' => $n > 0 ? $c / $n * 100 : null,
                    'n' => $n,
                    'conv' => $c,
                    'reliable' => $reliable,
                ];
            }

            $curBy = ($curRows[$src] ?? collect())->keyBy('manager_id');
            $curTotal = (int) $curBy->sum('n');

            $uncapped = $this->roundShares($weights);
            $shares = $src === 'call' && $callCaps !== null ? $this->roundShares($weights, $callCaps) : $uncapped;

            foreach ($list as $id => &$r) {
                $r['id'] = (int) $id;
                $r['share'] = $shares[$id];
                $r['capped_from'] = $shares[$id] < $uncapped[$id] ? $uncapped[$id] : null;
                $r['cur_n'] = (int) ($curBy[$id]->n ?? 0);
                $r['cur_total'] = $curTotal;
                $r['cur_share'] = $curTotal > 0 ? (int) round($r['cur_n'] / $curTotal * 100) : null;
                $r['delta'] = $r['cur_share'] === null ? null : $shares[$id] - $r['cur_share'];
                $r['phone'] = null;
                if ($src === 'call' && isset($phoneByMgr[$id])) {
                    $pd = (float) $phoneByMgr[$id]['calls_per_day'];
                    $r['phone'] = [
                        'per_day' => $pd,
                        'level' => $pd >= self::PHONE_CALLS_HIGH ? 'high' : ($pd >= self::PHONE_CALLS_LOW ? 'mid' : 'low'),
                    ];
                }
                $expected[$id] += $shares[$id] / 100 * ($totalN / $workDays);
            }
            unset($r);

            $list = array_values($list);
            usort($list, fn ($x, $y) => [$y['share'], $y['rate'] ?? -1] <=> [$x['share'], $x['rate'] ?? -1]);

            $blocks[] = ['key' => $src, 'title' => $label, 'cur_total' => $curTotal, 'avg' => $avg * 100, 'rows' => $list, 'unassigned' => 100 - array_sum($shares)];
        }

        $exp = [];
        foreach ($expected as $id => $perDay) {
            $exp[] = ['id' => (int) $id, 'name' => $short((int) $id), 'per_day' => round($perDay, 1), 'over' => $perDay > ManagerStatsService::LEADS_PER_DAY_NORM];
        }

        return ['blocks' => $blocks, 'expected' => $exp];
    }

    /**
     * Normalise weights to whole percents in STEP increments, after dropping
     * anyone below MIN_SHARE. Optional $caps (fraction 0..1 per key) limit a
     * share (phone capacity): a capped manager is pinned at his cap and the
     * surplus goes to the others proportionally to their weights. If every
     * manager is capped, the sum can stay below 100 (nobody can take more).
     *
     * @param  array<int|string,float>  $weights
     * @param  array<int|string,float>|null  $caps
     * @return array<int|string,int>
     */
    private function roundShares(array $weights, ?array $caps = null): array
    {
        $total = array_sum($weights);
        if ($total <= 0) {
            return array_fill_keys(array_keys($weights), 0);
        }

        // Drop the tail (below MIN_SHARE) before anything else.
        foreach ($weights as $k => $w) {
            if ($w / $total * 100 < self::REC_MIN_SHARE) {
                $weights[$k] = 0.0;
            }
        }

        $units = (int) (100 / self::REC_STEP);
        $capUnits = [];
        foreach ($weights as $k => $w) {
            $capUnits[$k] = $caps === null ? $units : (int) floor(($caps[$k] ?? 1.0) * $units + 1e-9);
        }

        // Water-filling: pin whoever exceeds the cap, spread the rest over the free ones.
        $pinned = [];
        do {
            $changed = false;
            $free = array_filter($weights, fn ($w, $k) => $w > 0 && ! isset($pinned[$k]), ARRAY_FILTER_USE_BOTH);
            $freeSum = array_sum($free);
            $room = $units - array_sum($pinned);
            foreach ($free as $k => $w) {
                if ($freeSum > 0 && $w / $freeSum * $room > $capUnits[$k]) {
                    $pinned[$k] = $capUnits[$k];
                    $changed = true;
                }
            }
        } while ($changed);

        $raw = [];
        $free = array_filter($weights, fn ($w, $k) => $w > 0 && ! isset($pinned[$k]), ARRAY_FILTER_USE_BOTH);
        $freeSum = array_sum($free) ?: 1.0;
        $room = $units - array_sum($pinned);
        foreach ($weights as $k => $w) {
            $raw[$k] = isset($pinned[$k]) ? (float) $pinned[$k] : ($w > 0 ? $w / $freeSum * $room : 0.0);
        }

        $base = array_map('floor', $raw);
        $left = $units - (int) array_sum($base);

        $rem = [];
        foreach ($raw as $k => $v) {
            $rem[$k] = $v - $base[$k];
        }
        arsort($rem);

        // Hand out the leftover units, never above a manager's cap.
        for ($pass = 0; $pass < 3 && $left > 0; $pass++) {
            foreach (array_keys($rem) as $k) {
                if ($left > 0 && $weights[$k] > 0 && $base[$k] + 1 <= $capUnits[$k]) {
                    $base[$k]++;
                    $left--;
                }
            }
        }

        return array_map(fn ($u) => (int) $u * self::REC_STEP, $base);
    }
}
