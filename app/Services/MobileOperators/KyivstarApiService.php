<?php

namespace App\Services\MobileOperators;

use App\Models\MobileOperatorAccount;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Kyivstar My Business B2B API (b2b-api.kyivstar.ua) wrapper — same role as
 * PrivatbankApiService for the bank module.
 *
 * Auth: OAuth2 client_credentials (client_id + client_secret from the
 * API portal) → Bearer access token, cached until shortly before expiry.
 *
 * Balance comes from GET /rest/billing-accounts. The response may contain
 * several billing accounts, so getBalance() returns the summed main amount.
 */
class KyivstarApiService
{
    private const BASE_URL = 'https://b2b-api.kyivstar.ua';

    private const TOKEN_URL = self::BASE_URL.'/idp/oauth2/token';

    // ──────────────────────────────────────────────
    // Auth
    // ──────────────────────────────────────────────

    /**
     * Fetch (or reuse cached) OAuth2 access token for the account.
     * Returns null when credentials are wrong or the API is unreachable.
     */
    public function getAccessToken(MobileOperatorAccount $account): ?string
    {
        $cacheKey = 'kyivstar_b2b_token_'.$account->id;

        $cached = Cache::get($cacheKey);

        if ($cached) {
            return $cached;
        }

        try {
            $response = Http::timeout(15)
                ->withBasicAuth($account->client_id, $account->client_secret)
                ->asForm()
                ->post(self::TOKEN_URL, ['grant_type' => 'client_credentials']);

            if (! $response->successful()) {
                Log::warning('Kyivstar token request failed', [
                    'account' => $account->display_name,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $token = $response->json('access_token');
            $expiresIn = (int) $response->json('expires_in', 300);

            if (! $token) {
                return null;
            }

            // Cache until 60s before expiry so we never send a stale token.
            Cache::put($cacheKey, $token, max($expiresIn - 60, 30));

            return $token;
        } catch (\Throwable $e) {
            Log::error('Kyivstar token connection error', [
                'account' => $account->display_name,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Credentials/reachability check for the View page: a successful
     * token grant means client_id/secret are valid and the API is up.
     *
     * @return array{ok: bool, message: string}
     */
    public function checkConnection(MobileOperatorAccount $account): array
    {
        return $this->getAccessToken($account)
            ? ['ok' => true, 'message' => 'З\'єднання працює: токен отримано, доступи дійсні.']
            : ['ok' => false, 'message' => 'Не вдалося отримати токен — перевірте client_id/client_secret або доступність API.'];
    }

    // ──────────────────────────────────────────────
    // Balance — the pluggable point (see class docblock)
    // ──────────────────────────────────────────────

    /**
     * @return array{amount: float, currency: string, accounts_count: int}|null
     *   null = balance not available because credentials/network/API failed.
     */
    public function getBalance(MobileOperatorAccount $account): ?array
    {
        $token = $this->getAccessToken($account);

        if (! $token) {
            return null;
        }

        try {
            $response = Http::timeout(20)
                ->withToken($token)
                ->acceptJson()
                ->get(self::BASE_URL.'/rest/billing-accounts', [
                    'states' => 'ACTIVE',
                    'size' => 1000,
                    'page' => 0,
                ]);

            if (! $response->successful()) {
                Log::warning('Kyivstar billing accounts request failed', [
                    'account' => $account->display_name,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $items = $response->json('content', []);

            if (! is_array($items)) {
                return null;
            }

            $amount = 0.0;
            $count = 0;

            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $amount += (float) data_get($item, 'balances.main.amount', 0);
                $count++;
            }

            return [
                'amount' => $amount,
                'currency' => 'UAH',
                'accounts_count' => $count,
            ];
        } catch (\Throwable $e) {
            Log::error('Kyivstar balance connection error', [
                'account' => $account->display_name,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
