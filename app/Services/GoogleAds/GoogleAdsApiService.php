<?php

namespace App\Services\GoogleAds;

use App\Models\GoogleAdsAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Google Ads API v25 wrapper, service-account auth only.
 *
 * Auth flow: the service account JSON key signs a JWT (RS256, scope
 * https://www.googleapis.com/auth/adwords), exchanged for a short-lived
 * OAuth access token, no Workspace domain-wide delegation needed — the
 * service account is added directly as a user on the target Ads account.
 * Developer tokens were sunset by Google on 2026-09-09; access is now
 * governed entirely by the Google Cloud project's access level
 * (Test/Explorer/Basic/Standard).
 *
 * checkConnection() is a read-only GAQL smoke test — the first implemented
 * call is deliberately lightweight, mirroring BinotelApiService::checkConnection.
 * It proves the credentials + access level work; it does not import or
 * write anything (no conversions, no outbox) — that is a later phase.
 */
class GoogleAdsApiService
{
    private const API_VERSION = 'v25';

    /**
     * @return array{ok: bool, message: string, customer_id?: string, descriptive_name?: string, raw?: mixed}
     */
    public function checkConnection(GoogleAdsAccount $account): array
    {
        try {
            $key = json_decode($account->service_account_json, true);

            if (! is_array($key) || ! isset($key['private_key'], $key['client_email'])) {
                return [
                    'ok' => false,
                    'message' => 'Невалідний JSON-ключ service account (немає private_key/client_email).',
                ];
            }

            $accessToken = $this->getAccessToken($key, $account);

            if ($accessToken === null) {
                return [
                    'ok' => false,
                    'message' => 'Не вдалося отримати OAuth-токен від Google (перевірте service account key).',
                ];
            }

            $response = Http::withToken($accessToken)
                ->timeout(20)
                ->acceptJson()
                ->asJson()
                ->post(
                    "https://googleads.googleapis.com/".self::API_VERSION."/customers/{$account->customer_id}/googleAds:search",
                    ['query' => 'SELECT customer.id, customer.descriptive_name FROM customer LIMIT 1'],
                );

            $json = $response->json();

            if (! $response->successful()) {
                Log::warning('Google Ads connection check failed', [
                    'account' => $account->display_name,
                    'status' => $response->status(),
                    'body' => $json ?? $response->body(),
                ]);

                return [
                    'ok' => false,
                    'message' => $json['error']['message'] ?? 'Google Ads API повернув помилку.',
                    'raw' => $json ?? $response->body(),
                ];
            }

            $customer = $json['results'][0]['customer'] ?? null;

            return [
                'ok' => true,
                'message' => 'Підключення працює: Google Ads API відповідає.',
                'customer_id' => $customer['id'] ?? $account->customer_id,
                'descriptive_name' => $customer['descriptiveName'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::error('Google Ads connection error', [
                'account' => $account->display_name,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'message' => 'Помилка з\'єднання з Google Ads API.',
            ];
        }
    }

    /**
     * Read-only GAQL query (all pages). Returns the raw `results` rows.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws \RuntimeException on auth / API errors
     */
    public function search(GoogleAdsAccount $account, string $query): array
    {
        $key = json_decode($account->service_account_json, true);

        if (! is_array($key) || ! isset($key['private_key'], $key['client_email'])) {
            throw new \RuntimeException('Невалідний JSON-ключ service account.');
        }

        $token = $this->getAccessToken($key, $account);

        if ($token === null) {
            throw new \RuntimeException('Не вдалося отримати OAuth-токен Google.');
        }

        $rows = [];
        $pageToken = null;

        do {
            $body = ['query' => $query] + ($pageToken ? ['pageToken' => $pageToken] : []);

            $response = Http::withToken($token)->timeout(60)->acceptJson()->asJson()->post(
                'https://googleads.googleapis.com/'.self::API_VERSION."/customers/{$account->customer_id}/googleAds:search",
                $body,
            );

            if (! $response->successful()) {
                throw new \RuntimeException($response->json('error.message') ?? ('Google Ads API HTTP '.$response->status()));
            }

            $rows = array_merge($rows, $response->json('results') ?? []);
            $pageToken = $response->json('nextPageToken');
        } while ($pageToken);

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $key  decoded service account JSON
     */
    private function getAccessToken(array $key, GoogleAdsAccount $account): ?string
    {
        $now = time();

        $segments = [
            $this->base64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])),
            $this->base64url(json_encode([
                'iss' => $key['client_email'],
                'scope' => 'https://www.googleapis.com/auth/adwords',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ])),
        ];

        $signingInput = implode('.', $segments);
        $signature = '';

        if (! openssl_sign($signingInput, $signature, $key['private_key'], 'SHA256')) {
            Log::error('Google Ads JWT signing failed (openssl_sign)', ['account' => $account->display_name]);

            return null;
        }

        $segments[] = $this->base64url($signature);
        $jwt = implode('.', $segments);

        $response = Http::asForm()
            ->timeout(20)
            ->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

        if (! $response->successful()) {
            Log::warning('Google Ads OAuth token exchange failed', [
                'account' => $account->display_name,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        return $response->json('access_token');
    }

    private function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
