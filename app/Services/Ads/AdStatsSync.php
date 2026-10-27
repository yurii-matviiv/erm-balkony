<?php

namespace App\Services\Ads;

use App\Models\GoogleAdsAccount;
use App\Services\GoogleAds\GoogleAdsApiService;
use Illuminate\Support\Facades\DB;

/**
 * Pulls campaigns (status, budget) and daily stats (cost, clicks, conversions,
 * search budget-lost impression share) from the Google Ads API. Read-only on Google's side.
 */
class AdStatsSync
{
    public function __construct(private GoogleAdsApiService $api) {}

    /**
     * @return array{campaigns: int, daily_rows: int}
     */
    public function run(int $days = 120): array
    {
        $account = GoogleAdsAccount::where('is_active', true)->first();

        if (! $account) {
            throw new \RuntimeException('Немає активного підключення Google Ads.');
        }

        $campaigns = $this->api->search($account, <<<'GAQL'
SELECT campaign.id, campaign.name, campaign.status, campaign.advertising_channel_type, campaign_budget.amount_micros
FROM campaign WHERE campaign.status != 'REMOVED'
GAQL);

        foreach ($campaigns as $row) {
            DB::table('ad_campaigns')->upsert([[
                'google_campaign_id' => (string) $row['campaign']['id'],
                'name' => $row['campaign']['name'],
                'status' => $row['campaign']['status'] ?? null,
                'channel_type' => $row['campaign']['advertisingChannelType'] ?? null,
                'budget_micros' => isset($row['campaignBudget']['amountMicros']) ? (int) $row['campaignBudget']['amountMicros'] : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]], ['google_campaign_id'], ['name', 'status', 'channel_type', 'budget_micros', 'updated_at']);
        }

        $lostFrom = now()->subDays(35)->toDateString();
        $lostTo = now()->toDateString();

        $lost = $this->api->search($account, <<<GAQL
SELECT campaign.id, metrics.search_budget_lost_impression_share
FROM campaign WHERE segments.date BETWEEN '{$lostFrom}' AND '{$lostTo}' AND campaign.status != 'REMOVED'
GAQL);

        DB::table('ad_campaigns')->update(['budget_lost_35d' => null]);

        foreach ($lost as $row) {
            if (isset($row['metrics']['searchBudgetLostImpressionShare'])) {
                DB::table('ad_campaigns')
                    ->where('google_campaign_id', (string) $row['campaign']['id'])
                    ->update(['budget_lost_35d' => round((float) $row['metrics']['searchBudgetLostImpressionShare'], 4)]);
            }
        }

        $from = now()->subDays($days)->toDateString();
        $to = now()->toDateString();

        $daily = $this->api->search($account, <<<GAQL
SELECT campaign.id, segments.date, metrics.cost_micros, metrics.clicks, metrics.conversions, metrics.search_budget_lost_impression_share
FROM campaign WHERE segments.date BETWEEN '{$from}' AND '{$to}' AND campaign.status != 'REMOVED'
GAQL);

        $rows = [];

        foreach ($daily as $row) {
            $m = $row['metrics'] ?? [];

            $rows[] = [
                'google_campaign_id' => (string) $row['campaign']['id'],
                'date' => $row['segments']['date'],
                'cost_micros' => (int) ($m['costMicros'] ?? 0),
                'clicks' => (int) ($m['clicks'] ?? 0),
                'conversions' => (float) ($m['conversions'] ?? 0),
                'budget_lost_share' => isset($m['searchBudgetLostImpressionShare']) ? round((float) $m['searchBudgetLostImpressionShare'], 4) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('ad_campaign_daily')->upsert($chunk, ['google_campaign_id', 'date'], ['cost_micros', 'clicks', 'conversions', 'budget_lost_share', 'updated_at']);
        }

        return ['campaigns' => count($campaigns), 'daily_rows' => count($rows)];
    }
}
