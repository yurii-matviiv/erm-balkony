<?php

namespace App\Filament\Pages;

use App\Services\Finance\FinanceDateRange;
use App\Services\Marketing\VacancyFormAnalyticsService;
use BackedEnum;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

/**
 * "Аналітика форми набору" — traffic/attribution view for the public
 * vacancy form (`/vacancy/{slug}`). Deliberately a separate page from
 * "Воронка вакансій" (which is about the hiring pipeline itself —
 * applications by vacancy/channel, no date filter): this page answers the
 * marketing question — how many times was the form opened, by how many
 * unique people, from which UTM source, and how does that trend day by
 * day — with the same date-range filter pattern as "Аналітика рахунків"
 * (see FinanceDateRange, reused as-is).
 *
 * "Сьогодні" card is always today's numbers regardless of the selected
 * filter — same reasoning as the live mobile-operator balances on the
 * finance analytics page: one number that doesn't need the filter touched
 * to be useful while a campaign is actively running.
 */
class VacancyFormAnalytics extends Page
{
    use \App\Filament\Concerns\RequiresViewPermission;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static string|\UnitEnum|null $navigationGroup = 'Найм';

    protected static ?string $navigationLabel = 'Аналітика форми';

    protected static ?string $title = 'Аналітика форми набору';

    protected static ?string $slug = 'vacancy-form-analytics';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.pages.vacancy-form-analytics';

    // ──────────────────────────────────────────────
    // Filter state (persisted in URL for shareability, same as InvoiceAnalytics)
    // ──────────────────────────────────────────────

    #[Url]
    public string $preset = 'last_30_days';

    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    /** '' = усі вакансії. String (not int) because Livewire #[Url] binds to the raw <select> value. */
    #[Url]
    public string $vacancyId = '';

    public function mount(): void
    {
        $range = FinanceDateRange::parseDateRange($this->preset);
        if (empty($this->dateFrom)) {
            $this->dateFrom = $range['from'];
        }
        if (empty($this->dateTo)) {
            $this->dateTo = $range['to'];
        }
    }

    public function applyPreset(string $preset): void
    {
        $this->preset = $preset;

        if ($preset !== 'custom') {
            $range = FinanceDateRange::parseDateRange($preset);
            $this->dateFrom = $range['from'];
            $this->dateTo = $range['to'];
        }
    }

    public function dateChanged(): void
    {
        $this->preset = 'custom';
    }

    protected function getViewData(): array
    {
        $range = FinanceDateRange::parseDateRange($this->preset, $this->dateFrom, $this->dateTo);
        $vacancyId = $this->vacancyId !== '' ? (int) $this->vacancyId : null;

        /** @var VacancyFormAnalyticsService $service */
        $service = app(VacancyFormAnalyticsService::class);

        $dailyResult = $service->getDailyBreakdown($range, $vacancyId);

        return [
            // Filter state
            'preset' => $this->preset,
            'dateFrom' => $range['from'],
            'dateTo' => $range['to'],
            'presetOptions' => FinanceDateRange::presetOptions(),
            'presetLabel' => FinanceDateRange::presetLabel($this->preset),
            'vacancyId' => $this->vacancyId,
            'vacancyOptions' => $service->vacancyOptions(),

            // Always-live "today" card, independent of the filter above
            'today' => $service->getTodaySummary($vacancyId),

            // Filtered-period data
            'summary' => $service->getSummary($range, $vacancyId),
            'sources' => $service->getSourceBreakdown($range, $vacancyId),
            'dailyRows' => $dailyResult['days'],
            'dailyTruncated' => $dailyResult['truncated'],
        ];
    }
}
