<?php

namespace App\Filament\Pages\Marketing;

use App\Services\Ads\AdBudgetAnalyzer;
use App\Services\Ads\AdStatsSync;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
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

    protected string $view = 'filament.pages.marketing.ad-budget-allocation';

    /** utm_campaign => chosen google campaign id (unmapped form) */
    public array $pick = [];

    public function refreshStats(): void
    {
        try {
            $res = app(AdStatsSync::class)->run(120);
            Notification::make()->title('Оновлено з Google Ads: кампаній '.$res['campaigns'].', рядків '.$res['daily_rows'])->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Не вдалося оновити: '.$e->getMessage())->danger()->send();
        }
    }

    public function mapCampaign(string $utm): void
    {
        $gid = $this->pick[$utm] ?? null;

        if (! $gid) {
            return;
        }

        DB::table('ad_campaign_mappings')->updateOrInsert(
            ['utm_campaign' => $utm],
            ['google_campaign_id' => $gid, 'confirmed' => true, 'updated_at' => now(), 'created_at' => now()],
        );
        unset($this->pick[$utm]);
    }

    public function confirmMapping(int $id): void
    {
        DB::table('ad_campaign_mappings')->where('id', $id)->update(['confirmed' => true, 'updated_at' => now()]);
    }

    public function removeMapping(int $id): void
    {
        DB::table('ad_campaign_mappings')->where('id', $id)->delete();
    }

    protected function getViewData(): array
    {
        return ['data' => app(AdBudgetAnalyzer::class)->analyze()];
    }
}
