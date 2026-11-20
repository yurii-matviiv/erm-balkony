<?php

namespace App\Filament\Pages;

use App\Services\Binotel\BinotelCallLoadService;
use App\Services\ManagerConversion\ManagerComparisonService;
use App\Services\ManagerConversion\ManagerStatsService;
use App\Services\ManagerConversion\PhoneLoadService;
use App\Services\ManagerConversion\RecommendationService;
use App\Services\Finance\FinanceDateRange;
use App\Filament\Concerns\HasInstructionModal;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;
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

    use \App\Filament\Concerns\RequiresViewPermission;
    use HasInstructionModal;

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

    /** Selected manager ids; empty = all managers. Applied only when showing: the cached numbers always cover everyone. @var list<int|string> */
    #[Url]
    public array $managerIds = [];

    /** «Порівняти періоди»: a second period (same fields as the first) and exactly one manager. */
    #[Url]
    public bool $compare = false;

    #[Url]
    public string $preset2 = 'custom';

    #[Url]
    public string $dateFrom2 = '';

    #[Url]
    public string $dateTo2 = '';

    /** The single manager shown in compare mode. */
    #[Url]
    public string $compareManagerId = '';

    /** Binotel load of the second period (compare mode), loaded lazily like the first. */
    public ?array $binotel2 = null;

    public string $binotelKey2 = '';

    /** Binotel phone workload, loaded lazily after the page renders (the API is slow). */
    public ?array $binotel = null;

    public string $binotelKey = '';

    public function loadBinotel(bool $fresh = false): void
    {
        [$from, $to] = $this->period();

        $this->binotel = app(BinotelCallLoadService::class)->forPeriod($from, $to, $fresh);
        $this->binotelKey = $from.'|'.$to;
    }

    public function loadBinotel2(): void
    {
        [$from, $to] = $this->period2();

        $this->binotel2 = app(BinotelCallLoadService::class)->forPeriod($from, $to);
        $this->binotelKey2 = $from.'|'.$to;
    }

    /** @return array{0:string,1:string} */
    private function period2(): array
    {
        [$a, $b] = $this->period();
        $len = \Carbon\Carbon::parse($a)->diffInDays(\Carbon\Carbon::parse($b)) + 1;

        $from = $this->dateFrom2 ?: \Carbon\Carbon::parse($a)->subDays($len)->toDateString();
        $to = $this->dateTo2 ?: \Carbon\Carbon::parse($a)->subDay()->toDateString();

        return $from > $to ? [$to, $from] : [$from, $to];
    }

    /** Turning compare on: periods read from past to future — period 1 is the one right BEFORE the chosen period, period 2 is the chosen one. */
    public function updatedCompare(bool $on): void
    {
        if (! $on) {
            return;
        }

        if ($this->dateFrom2 === '' || $this->dateTo2 === '') {
            [$prevFrom, $prevTo] = $this->period2(); // the period before the current one (same length)
            [$curFrom, $curTo] = $this->period();
            $curPreset = $this->preset;

            $this->dateFrom2 = $curFrom;
            $this->dateTo2 = $curTo;
            $this->preset2 = $curPreset;
            $this->dateFrom = $prevFrom;
            $this->dateTo = $prevTo;
            $this->preset = 'custom';
        }

        if ($this->compareManagerId === '') {
            $first = (int) ($this->managerIds[0] ?? 0);
            $managers = app(ManagerStatsService::class)->forPeriod(...$this->period())['managers'];
            $this->compareManagerId = (string) ($first ?: ($managers[0]['id'] ?? ''));
        }
    }

    public function applyPreset2(string $preset): void
    {
        $this->preset2 = $preset;

        if ($preset !== 'custom') {
            $range = $this->resolveRange($preset);
            $this->dateFrom2 = $range['from'];
            $this->dateTo2 = $range['to'];
        }
    }

    public function dateChanged2(): void
    {
        $this->preset2 = 'custom';
    }

    /** Empty $managerIds means "all managers" (all boxes shown as checked). */
    public function selectAllManagers(): void
    {
        $this->managerIds = [];
    }

    public function toggleManager(int $id): void
    {
        $all = array_map('intval', array_column(app(ManagerStatsService::class)->forPeriod(...$this->period())['managers'], 'id'));
        $chosen = $this->managerIds === [] ? $all : array_map('intval', $this->managerIds);

        $chosen = in_array($id, $chosen, true) ? array_values(array_diff($chosen, [$id])) : [...$chosen, $id];

        // Nothing chosen or everyone chosen = no filter.
        $this->managerIds = ($chosen === [] || count(array_intersect($all, $chosen)) === count($all)) ? [] : array_values($chosen);
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

    /** «Інструкція» in the header of the «Кого на що ставити» block (full-screen modal). */
    public function recommendationInstructionAction(): Action
    {
        return $this->instructionAction(
            'recommendationInstruction',
            'Розподіл заявок між менеджерами — як це працює',
            'filament.pages.manager-conversion-recommendation-instruction',
            [
                'windowDays' => RecommendationService::REC_WINDOW_DAYS,
                'skipDays' => RecommendationService::REC_SKIP_RECENT_DAYS,
                'currentDays' => RecommendationService::REC_CURRENT_DAYS,
                'minLeads' => RecommendationService::REC_MIN_LEADS,
                'step' => RecommendationService::REC_STEP,
                'minShare' => RecommendationService::REC_MIN_SHARE,
                'callsHigh' => RecommendationService::PHONE_CALLS_HIGH,
                'callsLow' => RecommendationService::PHONE_CALLS_LOW,
                'norm' => ManagerStatsService::LEADS_PER_DAY_NORM,
                'noChange' => RecommendationService::REC_NOCHANGE_PP,
            ],
        );
    }

    protected function getViewData(): array
    {
        [$from, $to] = $this->period();

        $base = app(ManagerStatsService::class)->forPeriod($from, $to);

        ['adSpend' => $adSpend, 'totalLeads' => $totalLeads, 'costPerLead' => $costPerLead, 'managers' => $managers] = $base;

        $phone = $this->binotelKey === $from.'|'.$to && ($this->binotel['ok'] ?? false)
            ? app(PhoneLoadService::class)->perManager($managers, $this->binotel['employees'], $from, $to)
            : [];

        $compareData = $this->compare ? app(ManagerComparisonService::class)->compare(
            (int) $this->compareManagerId,
            $managers,
            [$from, $to],
            $this->period2(),
            ['key' => $this->binotelKey, 'state' => $this->binotel],
            ['key' => $this->binotelKey2, 'state' => $this->binotel2],
        ) : null;

        // Recommendations are shares among ALL managers, so they are computed for everyone (and cached once);
        // the manager filter only narrows what is shown.
        $rec = Cache::remember(
            "manager-conversion:rec:v2:{$from}:{$to}:{$base['computedAt']}:".md5(json_encode($phone)),
            now()->addMinutes(ManagerStatsService::CACHE_MINUTES),
            fn () => app(RecommendationService::class)->recommend($managers, $phone, $from, $to),
        );

        $managerOptions = array_column($managers, 'name', 'id');
        // Only ids that exist for this period count (a manager picked earlier may have no data in a new period).
        $sel = array_values(array_filter(array_map('intval', $this->managerIds), fn ($id) => isset($managerOptions[$id])));

        $blocks = $rec['blocks'];
        $expected = $rec['expected'];
        $shownManagers = $managers;
        $shownPhone = $phone;

        if ($sel !== []) {
            $shownManagers = array_values(array_filter($managers, fn ($m) => in_array($m['id'], $sel, true)));
            $shownPhone = array_values(array_filter($phone, fn ($r) => in_array($r['id'], $sel, true)));
            $expected = array_values(array_filter($expected, fn ($e) => in_array($e['id'], $sel, true)));
            foreach ($blocks as &$b) {
                $b['rows'] = array_values(array_filter($b['rows'], fn ($r) => in_array($r['id'], $sel, true)));
                $b['unassigned'] = 0;
            }
            unset($b);
        }

        return [
            'compare' => $this->compare,
            'compareData' => $compareData,
            'preset2' => $this->preset2,
            'dateFrom2' => $this->dateFrom2 ?: ($compareData['period2'][0] ?? ''),
            'dateTo2' => $this->dateTo2 ?: ($compareData['period2'][1] ?? ''),
            'binotelKey2' => $compareData ? implode('|', $compareData['period2']) : '',
            'compareManagerId' => $this->compareManagerId,
            'managerOptions' => $managerOptions,
            'selectedManagers' => array_map(fn ($id) => $managerOptions[$id], $sel),
            'recommendations' => $blocks,
            'expected' => $expected,
            'binotelKey' => $from.'|'.$to,
            'binotelState' => $this->binotelKey === $from.'|'.$to ? $this->binotel : null,
            'phone' => $shownPhone,
            'norm' => ManagerStatsService::LEADS_PER_DAY_NORM,
            'budgetPercent' => (int) round(ManagerStatsService::AD_BUDGET_SHARE * 100),
            'adSpend' => $adSpend,
            'totalLeads' => $totalLeads,
            'costPerLead' => $costPerLead,
            'preset' => $this->preset,
            'dateFrom' => $from,
            'dateTo' => $to,
            'presetOptions' => self::presetOptions(),
            'managers' => $shownManagers,
            'computedAt' => $base['computedAt'],
        ];
    }
}
