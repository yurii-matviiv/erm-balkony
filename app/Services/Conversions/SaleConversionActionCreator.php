<?php

namespace App\Services\Conversions;

use App\Models\GoogleAdsAccount;
use Illuminate\Support\Facades\Http;

/**
 * Creates (once, idempotently) the secondary "sale" conversion action in Google Ads.
 * Fixed parameters on purpose — this class cannot create anything else:
 * UPLOAD_CLICKS, category PURCHASE (not a biddable account goal), MANY_PER_CLICK
 * (allows gbraid/wbraid and re-sending a changed order total), 90-day window,
 * primary_for_goal = false (does not influence bidding).
 * Auth: service account, scope `adwords` (Google Ads API; developer tokens are abolished).
 */
class SaleConversionActionCreator
{
    public const NAME = 'erm_sale_offline';

    private const API = 'https://googleads.googleapis.com/v25';

    /**
     * @return array{ok: bool, message: string, id?: string, existing?: bool}
     */
    public function run(GoogleAdsAccount $account, bool $validateOnly): array
    {
        $token = $this->token($account);
        $customer = $account->customer_id;

        $search = Http::withToken($token)->timeout(30)->acceptJson()->asJson()->post(
            self::API."/customers/{$customer}/googleAds:search",
            ['query' => "SELECT conversion_action.id, conversion_action.status FROM conversion_action WHERE conversion_action.name = '".self::NAME."'"],
        );

        if (! $search->successful()) {
            return ['ok' => false, 'message' => 'Пошук дії: '.($search->json('error.message') ?? $search->status())];
        }

        foreach ($search->json('results') ?? [] as $row) {
            if (($row['conversionAction']['status'] ?? '') !== 'REMOVED') {
                return ['ok' => true, 'existing' => true, 'id' => (string) $row['conversionAction']['id'], 'message' => 'Дія вже існує.'];
            }
        }

        $response = Http::withToken($token)->timeout(30)->acceptJson()->asJson()->post(
            self::API."/customers/{$customer}/conversionActions:mutate",
            [
                'operations' => [[
                    'create' => [
                        'name' => self::NAME,
                        'type' => 'UPLOAD_CLICKS',
                        'category' => 'PURCHASE',
                        'status' => 'ENABLED',
                        'countingType' => 'MANY_PER_CLICK',
                        'clickThroughLookbackWindowDays' => 90,
                        'primaryForGoal' => false,
                        'attributionModelSettings' => ['attributionModel' => 'GOOGLE_ADS_LAST_CLICK'],
                        'valueSettings' => [
                            'defaultValue' => 1,
                            'defaultCurrencyCode' => config('conversions.currency', 'UAH'),
                            'alwaysUseDefaultValue' => false,
                        ],
                    ],
                ]],
                'validateOnly' => $validateOnly,
            ],
        );

        if (! $response->successful()) {
            return ['ok' => false, 'message' => 'Створення: '.json_encode($response->json('error.details') ?? $response->json('error.message') ?? $response->status(), JSON_UNESCAPED_UNICODE)];
        }

        if ($validateOnly) {
            return ['ok' => true, 'message' => 'validateOnly пройдено, дію не створено.'];
        }

        $resource = (string) ($response->json('results.0.resourceName') ?? '');

        return ['ok' => true, 'id' => basename($resource), 'message' => "Створено: {$resource}"];
    }

    private function token(GoogleAdsAccount $account): string
    {
        $key = json_decode((string) $account->service_account_json, true);

        if (! is_array($key) || ! isset($key['private_key'], $key['client_email'])) {
            throw new \RuntimeException('Невалідний JSON-ключ service account.');
        }

        $b64 = fn (string $d) => rtrim(strtr(base64_encode($d), '+/', '-_'), '=');
        $now = time();
        $head = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claim = $b64(json_encode([
            'iss' => $key['client_email'],
            'scope' => 'https://www.googleapis.com/auth/adwords',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ]));

        $signature = '';
        openssl_sign("{$head}.{$claim}", $signature, $key['private_key'], 'SHA256');

        $response = Http::asForm()->timeout(20)->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => "{$head}.{$claim}.".$b64($signature),
        ]);

        $token = $response->json('access_token');

        if (! $token) {
            throw new \RuntimeException('Google не видав токен: '.($response->json('error') ?? $response->status()));
        }

        return $token;
    }
}
