<?php

namespace App\Filament\Pages\Marketing;

use App\Services\Ads\AdBudgetAnalyzer;
use App\Services\Ads\AdStatsSync;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;

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

    public function refreshStats(): void
    {
        try {
            $res = app(AdStatsSync::class)->run(120);
            Notification::make()->title('Оновлено з Google Ads: кампаній '.$res['campaigns'].', рядків '.$res['daily_rows'])->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Не вдалося оновити: '.$e->getMessage())->danger()->send();
        }
    }

    protected function getViewData(): array
    {
        return ['data' => app(AdBudgetAnalyzer::class)->analyze()];
    }
}
