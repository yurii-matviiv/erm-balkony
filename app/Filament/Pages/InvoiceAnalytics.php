<?php

namespace App\Filament\Pages;

use App\Services\Finance\FinanceAnalyticsService;
use App\Services\Finance\FinanceDateRange;
use BackedEnum;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

/**
 * "Аналітика рахунків" — the company's financial overview page.
 *
 * Structure:
 *   1. KPI indicators (income / expenses / result) — always for the active period
 *   2. Single date filter (preset dropdown + from/to date pickers)
 *   3. Income table: cash vs cashless, CRM vs PrivatBank cross-check
 *   4. Expenses table: grouped (production / office / telephone / marketing / tax / salary)
 *
 * All sections update together when the filter changes — there is only one
 * filter for the whole page. Default period: last 30 days.
 *
 * Date handling follows the two-step pattern in FinanceDateRange:
 *   parseDateRange() → neutral Y-m-d range
 *   formatForCrm()   → MySQL datetime strings
 *   formatForPrivatBank() → dd-mm-YYYY for the API
 */
class InvoiceAnalytics extends Page
{
    use \App\Filament\Concerns\RequiresViewPermission;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationLabel = 'Аналітика рахунків';

    protected static ?string $title = 'Аналітика рахунків';

    protected static ?string $slug = 'invoice-analytics';

    protected static string|\UnitEnum|null $navigationGroup = 'Фінанси';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.invoice-analytics';

    // ──────────────────────────────────────────────
    // Filter state (persisted in URL for shareability)
    // ──────────────────────────────────────────────

    #[Url]
    public string $preset = 'last_30_days';

    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    // ──────────────────────────────────────────────
    // Lifecycle
    // ──────────────────────────────────────────────

    public function mount(): void
    {
        // Initialise date inputs from the default preset so the UI
        // always shows a concrete period, not empty fields.
        $range = FinanceDateRange::parseDateRange($this->preset);
        if (empty($this->dateFrom)) {
            $this->dateFrom = $range['from'];
        }
        if (empty($this->dateTo)) {
            $this->dateTo = $range['to'];
        }
    }

    // ──────────────────────────────────────────────
    // Livewire actions (called from the Blade view)
    // ──────────────────────────────────────────────

    /**
     * Called when the user picks a preset from the dropdown.
     * Updates the date inputs to match the preset's concrete range.
     */
    public function applyPreset(string $preset): void
    {
        $this->preset = $preset;

        if ($preset !== 'custom') {
            $range          = FinanceDateRange::parseDateRange($preset);
            $this->dateFrom = $range['from'];
            $this->dateTo   = $range['to'];
        }
        // If 'custom', leave the date inputs as they are — user will type them.
    }

    /**
     * Called when the user manually changes a date input.
     * Switches the preset to 'custom' automatically.
     */
    public function dateChanged(): void
    {
        $this->preset = 'custom';
    }

    // ──────────────────────────────────────────────
    // Data passed to the view
    // ──────────────────────────────────────────────

    protected function getViewData(): array
    {
        $range = FinanceDateRange::parseDateRange($this->preset, $this->dateFrom, $this->dateTo);

        /** @var FinanceAnalyticsService $service */
        $service = app(FinanceAnalyticsService::class);

        return [
            // Filter state
            'preset'         => $this->preset,
            'dateFrom'       => $range['from'],
            'dateTo'         => $range['to'],
            'presetOptions'  => FinanceDateRange::presetOptions(),
            'presetLabel'    => FinanceDateRange::presetLabel($this->preset),

            // KPI
            'kpi'            => $service->getKpiTotals($range),

            // Income section
            'income'         => $service->getIncomeData($range),

            // Expenses section
            'expenses'       => $service->getExpensesData($range),
        ];
    }
}
