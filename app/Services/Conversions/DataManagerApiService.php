<?php

namespace App\Services\Conversions;

use App\Models\GoogleAdsAccount;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Google Data Manager API client (events:ingest + requestStatus:retrieve).
 * Service-account auth with scope `datamanager` (the `adwords` scope is rejected
 * by this API — verified 2026-10-20). Secrets are never logged.
 */
class DataManagerApiService
{
    private const SCOPE = 'https://www.googleapis.com/auth/datamanager';

    private const BASE = 'https://datamanager.googleapis.com/v1';

    public function activeAccount(): ?GoogleAdsAccount
    {
        return GoogleAdsAccount::where('is_active', true)->orderBy('id')->first();
    }

    /**
     * @param  array<int, array<string, mixed>>  $events  already in Data Manager event format
     * @return string request id
     *
     * @throws \RuntimeException on any API / auth error (message has no secrets)
     */
    public function ingest(GoogleAdsAccount $account, string $conversionActionId, array $events, bool $validateOnly): string
    {
        $response = Http::withToken($this->accessToken($account))
            ->timeout(30)
            ->acceptJson()
            ->asJson()
            ->post(self::BASE.'/events:ingest', [
                'destinations' => [[
                    'operatingAccount' => ['accountType' => 'GOOGLE_ADS', 'accountId' => $account->customer_id],
                    'productDestinationId' => $conversionActionId,
                ]],
                'encoding' => 'HEX',
                'consent' => [
                    'adUserData' => 'CONSENT_GRANTED',
                    'adPersonalization' => 'CONSENT_GRANTED',
                ],
                'events' => $events,
                'validateOnly' => $validateOnly,
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException($this->errorMessage($response->status(), $response->json(), $response->body()));
        }

        return (string) ($response->json('requestId') ?? '');
    }

    /**
     * Generic authorised POST to the Data Manager API (audiences etc.).
     *
     * @param  array<string, mixed>  $body
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     *
     * @throws \RuntimeException on any API / auth error (message has no secrets)
     */
    public function postJson(GoogleAdsAccount $account, string $path, array $body, array $query = []): array
    {
        $url = self::BASE.'/'.ltrim($path, '/').($query !== [] ? '?'.http_build_query($query) : '');

        $response = Http::withToken($this->accessToken($account))
            ->timeout(120)
            ->acceptJson()
            ->asJson()
            ->post($url, $body);

        if (! $response->successful()) {
            throw new \RuntimeException($this->errorMessage($response->status(), $response->json(), $response->body()));
        }

        return $response->json() ?? [];
    }

    /**
     * @return array<string, mixed> the first (only) per-destination status
     */
    public function requestStatus(GoogleAdsAccount $account, string $requestId): array
    {
        $response = Http::withToken($this->accessToken($account))
            ->timeout(30)
            ->acceptJson()
            ->get(self::BASE.'/requestStatus:retrieve', ['requestId' => $requestId]);

        if (! $response->successful()) {
            throw new \RuntimeException($this->errorMessage($response->status(), $response->json(), $response->body()));
        }

        return $response->json('requestStatusPerDestination.0') ?? [];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, array<string, string>>  $userIdentifiers hashed phone/email
     * @return array<string, mixed>
     */
    public function buildEvent(array $row, array $userIdentifiers = []): array
    {
        $tz = config('conversions.timezone', 'Europe/Kyiv');

        $event = [
            'eventTimestamp' => \Carbon\Carbon::parse($row['event_time'])->setTimezone($tz)->toRfc3339String(),
            'transactionId' => $row['transaction_id'],
            'eventSource' => 'WEB',
            'conversionValue' => (float) $row['value'],
            'currency' => $row['currency'],
        ];

        if (($row['click_id_type'] ?? null) === 'gclid' && ! empty($row['click_id'])) {
            $event['adIdentifiers'] = ['gclid' => $row['click_id']];
        }

        if ($userIdentifiers !== []) {
            $event['userData'] = ['userIdentifiers' => $userIdentifiers];
        }

        return $event;
    }

    private function accessToken(GoogleAdsAccount $account): string
    {
        return Cache::remember('data_manager_token_'.$account->id, 3000, function () use ($account) {
            $key = json_decode((string) $account->service_account_json, true);

            if (! is_array($key) || ! isset($key['private_key'], $key['client_email'])) {
                throw new \RuntimeException('Невалідний JSON-ключ service account.');
            }

            $now = time();
            $head = $this->b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $claim = $this->b64(json_encode([
                'iss' => $key['client_email'],
                'scope' => self::SCOPE,
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ]));

            $signature = '';

            if (! openssl_sign("{$head}.{$claim}", $signature, $key['private_key'], 'SHA256')) {
                throw new \RuntimeException('Не вдалося підписати JWT.');
            }

            $response = Http::asForm()->timeout(20)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => "{$head}.{$claim}.".$this->b64($signature),
            ]);

            $token = $response->json('access_token');

            if (! $token) {
                throw new \RuntimeException('Google не видав токен: '.($response->json('error') ?? $response->status()));
            }

            return $token;
        });
    }

    private function b64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * @param  array<string, mixed>|null  $json
     */
    private function errorMessage(int $status, ?array $json, string $body): string
    {
        $message = $json['error']['message'] ?? mb_substr($body, 0, 300);

        return "Data Manager API HTTP {$status}: {$message}";
    }
}
