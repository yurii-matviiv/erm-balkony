<?php

namespace App\Filament\Pages;

use App\Models\Expense;
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
 *    for each lead he received. Allowed ad budget = AD_BUDGET_SHARE (5%) of
 *    his turnover. Result = allowed budget - charged ad cost (plus = fits
 *    the budget, minus = overspent). ДРР = charged ad cost / turnover.
 *    Approximation by design: ad payments are bank top-ups, not exact daily
 *    spend, and non-ad leads (calls, referrals) share the same average.
 */
class ManagerConversion extends Page
{
    /** Share of a manager's turnover that may be spent on advertising (business rule: 5%). */
    public const AD_BUDGET_SHARE = 0.05;

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
     * @return list<array{id:int,name:string,leads:int,converted:int,orders:int,rate:float,turnover:float,ad_cost:float,ad_budget:float,drr:?float,result:float}>
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

            $rows[] = [
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

    protected function getViewData(): array
    {
        $from = $this->dateFrom ?: now()->subDays(30)->toDateString();
        $to = $this->dateTo ?: now()->toDateString();

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

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
            'budgetPercent' => (int) round(self::AD_BUDGET_SHARE * 100),
            'adSpend' => $adSpend,
            'totalLeads' => $totalLeads,
            'costPerLead' => $costPerLead,
            'preset' => $this->preset,
            'dateFrom' => $from,
            'dateTo' => $to,
            'presetOptions' => self::presetOptions(),
            'managers' => $this->managers($from, $to, $costPerLead),
        ];
    }
}
