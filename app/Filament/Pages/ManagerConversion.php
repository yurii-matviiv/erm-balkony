<?php

namespace App\Filament\Pages;

use App\Models\Expense;
use App\Services\Binotel\BinotelCallLoadService;
use App\Services\Finance\FinanceDateRange;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;

/**
 * "Конверсія менеджерів" — one indicator card per active manager:
 * leads received, leads that became an order, conversion %, turnover.
 *
 * "Active" = the manager has at least one lead or order in the chosen
 * period (not a role/is_active check), so people with nothing to show
 * never clutter the page.
 *
 * Definitions (kept in one place so the numbers stay explainable):
 *  - Заявки: leads created in the period, assigned to the manager.
 *  - Замовлення (конверсія): those same leads that have at least one
 *    non-cancelled order. Cohort-based on purpose — conversion can never
 *    exceed 100% even when an order is created weeks after the lead.
 *  - Оборот: SUM(orders.total_price) of non-cancelled orders created in
 *    the period by the manager (the user's "прибуток" = оборот).
 *  - Реклама: all marketing expenses of the period (same filter as
 *    "Аналітика рахунків": outgo + received). Average ad cost per lead =
 *    that sum / ALL leads of the period; a manager is charged that average
 *    for each lead he received. Allowed ad budget = AD_BUDGET_SHARE (margin
 *    18% - salary 7% = 11%) of his turnover. Result = allowed budget - charged ad cost (plus = fits
 *    the budget, minus = overspent). ДРР = charged ad cost / turnover.
 *    Approximation by design: ad payments are bank top-ups, not exact daily
 *    spend, and non-ad leads (calls, referrals) share the same average.
 */
class ManagerConversion extends Page
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

    /** Recommendations use a fixed, mature window (independent of the date filter): orders appear weeks after a lead. */
    private const REC_WINDOW_DAYS = 90;
    private const REC_SKIP_RECENT_DAYS = 14;
    /** Fewer leads than this per manager+source = too noisy to judge. */
    private const REC_MIN_LEADS = 30;
    /** Conversion is pulled toward the company average by this many "virtual" leads (guards against 4-of-37 luck). */
    private const REC_PRIOR_LEADS = 30;
    /** Weight = (conversion / average)^POWER: higher = steeper skew toward the strongest. */
    private const REC_POWER = 2;
    /** A manager with too few leads of a source gets half weight until proven. */
    private const REC_UNPROVEN_WEIGHT = 0.5;
    /** Shares are rounded to this step (percent); a share below MIN becomes 0 = do not assign. */
    private const REC_STEP = 5;
    private const REC_MIN_SHARE = 10;
    /** Phone load (answered incoming + outgoing calls per working day, from Binotel). Business view: ~12/day is already a lot. */
    private const PHONE_CALLS_HIGH = 12;
    private const PHONE_CALLS_LOW = 6;

    /** What is left for advertising before a manager goes into the red: 18% - 7% = 11% of turnover. */
    public const AD_BUDGET_SHARE = self::ORDER_MARGIN - self::MANAGER_SALARY;

    use \App\Filament\Concerns\RequiresViewPermission;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Конверсія менеджерів';

    protected static ?string $title = 'Конверсія менеджерів';

    protected static ?string $slug = 'manager-conversion';

    protected static string|\UnitEnum|null $navigationGroup = 'Заявки';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.pages.manager-conversion';

    #[Url]
    public string $preset = 'last_30_days';

    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    /** Binotel phone workload, loaded lazily after the page renders (the API is slow). */
    public ?array $binotel = null;

    public string $binotelKey = '';

    public function loadBinotel(): void
    {
        [$from, $to] = $this->period();

        $this->binotel = app(BinotelCallLoadService::class)->forPeriod($from, $to);
        $this->binotelKey = $from.'|'.$to;
    }

    /** @return array{0:string,1:string} */
    private function period(): array
    {
        $from = $this->dateFrom ?: now()->subDays(30)->toDateString();
        $to = $this->dateTo ?: now()->toDateString();

        return $from > $to ? [$to, $from] : [$from, $to];
    }

    public function mount(): void
    {
        if ($this->dateFrom === '' || $this->dateTo === '') {
            $range = $this->resolveRange($this->preset);
            $this->dateFrom = $range['from'];
            $this->dateTo = $range['to'];
        }
    }

    public function applyPreset(string $preset): void
    {
        $this->preset = $preset;

        if ($preset !== 'custom') {
            $range = $this->resolveRange($preset);
            $this->dateFrom = $range['from'];
            $this->dateTo = $range['to'];
        }
    }

    public function dateChanged(): void
    {
        $this->preset = 'custom';
    }

    /** FinanceDateRange has no "tomorrow"; handled here to leave the shared helper untouched. */
    private function resolveRange(string $preset): array
    {
        if ($preset === 'tomorrow') {
            $d = now()->addDay()->toDateString();

            return ['from' => $d, 'to' => $d];
        }

        return FinanceDateRange::parseDateRange($preset);
    }

    public static function presetOptions(): array
    {
        return [
            'today' => 'Сьогодні',
            'tomorrow' => 'Завтра',
            'yesterday' => 'Вчора',
            'last_7_days' => 'Останні 7 днів',
            'last_30_days' => 'Останні 30 днів',
            'current_month' => 'Поточний місяць',
            'prev_month' => 'Попередній місяць',
            'custom' => 'Довільний період',
        ];
    }

    /**
     * Mon–Fri days in the period, but never beyond today (future days carry no leads yet).
     * Leads almost never arrive on weekends (Sat+Sun ≈ 4% in the data), so calendar days would understate the load.
     */
    private function workingDays(string $from, string $to): int
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
    protected function managers(string $from, string $to, float $costPerLead = 0.0): array
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

    /**
     * "Кого на що ставити": for each lead source (calls / site-ads) the
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
    protected function recommendations(array $managers, array $phone = [], ?string $from = null, ?string $to = null): array
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

        $workDays = max(1, $this->workingDays($from->toDateString(), $to->toDateString()));
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

            $uncapped = $this->roundShares($weights);
            $shares = $src === 'call' && $callCaps !== null ? $this->roundShares($weights, $callCaps) : $uncapped;

            foreach ($list as $id => &$r) {
                $r['id'] = (int) $id;
                $r['share'] = $shares[$id];
                $r['capped_from'] = $shares[$id] < $uncapped[$id] ? $uncapped[$id] : null;
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

            $blocks[] = ['title' => $label, 'avg' => $avg * 100, 'rows' => $list, 'unassigned' => 100 - array_sum($shares)];
        }

        $exp = [];
        foreach ($expected as $id => $perDay) {
            $exp[] = ['name' => $short((int) $id), 'per_day' => round($perDay, 1), 'over' => $perDay > self::LEADS_PER_DAY_NORM];
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

    /**
     * Binotel knows employees by name ("Анна SIP", "Ірина SIM"), ERM users by
     * "Прізвище Ім'я По батькові". A manager is matched by first name; one
     * person can have several Binotel endpoints (SIP + SIM) — they are summed.
     * Fine while active managers have distinct first names; a clash would
     * merge two people, so this is flagged in the UI note.
     *
     * @param  list<array<string,mixed>>  $managers
     * @param  array<string,array<string,mixed>>  $employees
     * @return list<array<string,mixed>>
     */
    private function phoneLoad(array $managers, array $employees, string $from, string $to): array
    {
        $workDays = max(1, $this->workingDays($from, $to));
        $out = [];

        foreach ($managers as $m) {
            $first = mb_strtolower(explode(' ', trim($m['name']))[1] ?? '');
            $t = ['in' => 0, 'out' => 0, 'out_try' => 0, 'sec' => 0];

            foreach ($employees as $e) {
                if ($first !== '' && mb_strtolower(explode(' ', trim($e['name']))[0]) === $first) {
                    $t['in'] += $e['in_answered'];
                    $t['out'] += $e['out_answered'];
                    $t['out_try'] += $e['out_attempts'];
                    $t['sec'] += $e['in_talk_sec'] + $e['out_talk_sec'];
                }
            }

            $out[] = [
                'id' => $m['id'],
                'name' => implode(' ', array_slice(explode(' ', trim($m['name'])), 0, 2)),
                'in' => $t['in'],
                'out' => $t['out'],
                'out_try' => $t['out_try'],
                'sec' => $t['sec'],
                'calls_per_day' => round(($t['in'] + $t['out']) / $workDays, 1),
                'hours_per_day' => round($t['sec'] / 3600 / $workDays, 1),
            ];
        }

        usort($out, fn ($a, $b) => $b['sec'] <=> $a['sec']);

        return $out;
    }

    protected function getViewData(): array
    {
        [$from, $to] = $this->period();

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

        $managers = $this->managers($from, $to, $costPerLead);

        $phone = $this->binotelKey === $from.'|'.$to && ($this->binotel['ok'] ?? false)
            ? $this->phoneLoad($managers, $this->binotel['employees'], $from, $to)
            : [];

        return [
            'binotelKey' => $from.'|'.$to,
            'binotelState' => $this->binotelKey === $from.'|'.$to ? $this->binotel : null,
            'phone' => $phone,
            'norm' => self::LEADS_PER_DAY_NORM,
            'budgetPercent' => (int) round(self::AD_BUDGET_SHARE * 100),
            'adSpend' => $adSpend,
            'totalLeads' => $totalLeads,
            'costPerLead' => $costPerLead,
            'preset' => $this->preset,
            'dateFrom' => $from,
            'dateTo' => $to,
            'presetOptions' => self::presetOptions(),
            'managers' => $managers,
            ...(function () use ($managers, $phone, $from, $to) {
                $rec = $this->recommendations($managers, $phone, $from, $to);

                return ['recommendations' => $rec['blocks'], 'expected' => $rec['expected']];
            })(),
        ];
    }
}
