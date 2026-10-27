<?php

namespace App\Filament\Pages\Marketing;

use App\Services\Ads\AdBudgetAnalyzer;
use App\Services\Ads\AdStatsSync;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\DB;

/**
 * "Розподіл бюджету реклами" — which Google Ads campaigns deserve a bigger / smaller share of
 * the budget, from Google spend (API) vs turnover of the clients each campaign brought (CRM).
 * Everything is shown in percent; the maths lives in AdBudgetAnalyzer.
 */
class AdBudgetAllocation extends Page
{
    use \App\Filament\Concerns\RequiresViewPermission;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationLabel = 'Розподіл бюджету реклами';

    protected static ?string $title = 'Розподіл бюджету реклами';

    protected static ?string $slug = 'ad-budget-allocation';

    protected static string|\UnitEnum|null $navigationGroup = 'Маркетинг';

    protected static ?int $navigationSort = 20;

    protected Width|string|null $maxContentWidth = Width::Full;

    protected string $view = 'filament.pages.marketing.ad-budget-allocation';

    /** Data is considered fresh for this many minutes; the page refreshes it from Google on open when older. */
    private const FRESH_MINUTES = 10;

    public function mount(): void
    {
        $last = DB::table('ad_campaigns')->max('updated_at');

        if ($last !== null && now()->diffInMinutes(\Illuminate\Support\Carbon::parse($last), true) < self::FRESH_MINUTES) {
            return;
        }

        try {
            app(AdStatsSync::class)->run(120);
        } catch (\Throwable $e) {
            Notification::make()->title('Не вдалося оновити дані з Google (показано останні збережені): '.$e->getMessage())->warning()->send();
        }
    }

    protected function getViewData(): array
    {
        return ['data' => app(AdBudgetAnalyzer::class)->analyze()];
    }
}
