<?php

namespace App\Services\Binotel;

use App\Models\BinotelAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Binotel API v4 wrapper.
 *
 * Binotel endpoints accept POST JSON with `companyID`, `key` and `secret`.
 * The first implemented endpoint is deliberately lightweight:
 * settings/list-of-employees is used as a live connection check because it
 * proves the credentials work and returns stable account data without
 * importing calls yet.
 */
class BinotelApiService
{
    private const BASE_URL = 'https://api.binotel.com/api/4.0';

    /**
     * @return array{ok: bool, message: string, employees_count?: int}
     */
    public function checkConnection(BinotelAccount $account): array
    {
        try {
            $response = Http::timeout(20)
                ->acceptJson()
                ->asJson()
                ->post(self::BASE_URL.'/settings/list-of-employees.json', [
                    'companyID' => $account->company_id,
                    'key' => $account->api_key,
                    'secret' => $account->api_secret,
                ]);

            if (! $response->successful()) {
                Log::warning('Binotel connection check failed', [
                    'account' => $account->display_name,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'ok' => false,
                    'message' => 'Не вдалося підключитись до Binotel API. Перевірте companyID, ключ і секрет.',
                ];
            }

            $json = $response->json();

            if (($json['status'] ?? null) !== 'success') {
                Log::warning('Binotel connection check returned API error', [
                    'account' => $account->display_name,
                    'response' => $json,
                ]);

                return [
                    'ok' => false,
                    'message' => $json['message'] ?? 'Binotel API повернув помилку авторизації.',
                ];
            }

            $employees = $json['employeeData'] ?? [];

            return [
                'ok' => true,
                'message' => 'Підключення працює: Binotel API відповідає.',
                'employees_count' => is_array($employees) ? count($employees) : 0,
            ];
        } catch (\Throwable $e) {
            Log::error('Binotel connection error', [
                'account' => $account->display_name,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'message' => 'Помилка зʼєднання з Binotel API.',
            ];
        }
    }
}
