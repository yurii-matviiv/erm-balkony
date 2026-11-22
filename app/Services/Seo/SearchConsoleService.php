<?php

namespace App\Services\Seo;

use Illuminate\Support\Facades\Http;

/**
 * Тонка обгортка Search Console API (service account, лише читання).
 * JWT RS256 → access token → searchAnalytics/query та urlInspection. Ключ читається зі шляху, не логується.
 */
class SearchConsoleService
{
    private ?string $token = null;

    private int $tokenExpires = 0;

    public function siteUrl(): string
    {
        return config('seo.site_url');
    }

    /** @return array<int, array{keys: array<int,string>, clicks: float, impressions: float, ctr: float, position: float}> */
    public function query(string $start, string $end, array $dimensions, int $rowLimit = 1000, array $filters = []): array
    {
        $body = ['startDate' => $start, 'endDate' => $end, 'dimensions' => $dimensions, 'rowLimit' => $rowLimit];
        if ($filters) {
            $body['dimensionFilterGroups'] = [['groupType' => 'and', 'filters' => $filters]];
        }

        $url = 'https://www.googleapis.com/webmasters/v3/sites/'.rawurlencode($this->siteUrl()).'/searchAnalytics/query';
        $res = Http::withToken($this->accessToken())->timeout(40)->acceptJson()->post($url, $body);

        if (! $res->successful()) {
            throw new \RuntimeException('Search Console: HTTP '.$res->status().' '.($res->json('error.message') ?? ''));
        }

        return $res->json('rows') ?? [];
    }

    /** @return array{coverage_state: ?string, last_crawl_time: ?string, google_canonical: ?string} */
    public function inspect(string $url): array
    {
        $res = Http::withToken($this->accessToken())->timeout(40)->acceptJson()
            ->post('https://searchconsole.googleapis.com/v1/urlInspection/index:inspect', [
                'inspectionUrl' => $url,
                'siteUrl' => $this->siteUrl(),
            ]);

        if (! $res->successful()) {
            throw new \RuntimeException('URL Inspection: HTTP '.$res->status().' '.($res->json('error.message') ?? ''));
        }

        $r = $res->json('inspectionResult.indexStatusResult') ?? [];

        return [
            'coverage_state' => $r['coverageState'] ?? null,
            'last_crawl_time' => $r['lastCrawlTime'] ?? null,
            'google_canonical' => $r['googleCanonical'] ?? null,
        ];
    }

    private function accessToken(): string
    {
        if ($this->token && time() < $this->tokenExpires - 60) {
            return $this->token;
        }

        $path = config('seo.key_path');
        $key = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        if (! is_array($key) || ! isset($key['private_key'], $key['client_email'])) {
            throw new \RuntimeException('Ключ Search Console не знайдено або він невалідний (GOOGLE_SC_KEY_PATH).');
        }

        $b64 = fn (string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        $now = time();
        $unsigned = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])).'.'.$b64(json_encode([
            'iss' => $key['client_email'],
            'scope' => 'https://www.googleapis.com/auth/webmasters.readonly',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ]));
        openssl_sign($unsigned, $sig, $key['private_key'], OPENSSL_ALGO_SHA256);

        $res = Http::asForm()->timeout(20)->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $unsigned.'.'.$b64($sig),
        ]);

        if (! $res->successful() || ! $res->json('access_token')) {
            throw new \RuntimeException('Google не видав токен: '.($res->json('error') ?? $res->status()));
        }

        $this->tokenExpires = $now + (int) ($res->json('expires_in') ?? 3600);

        return $this->token = $res->json('access_token');
    }
}
