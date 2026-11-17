<?php

namespace App\Services\Leads;

use App\Models\BinotelAccount;
use App\Models\Lead;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Крок «first_call» (перший вихідний дзвінок клієнту) — факт із часом у журналі кроків.
 *
 * Викликається при відкритті заявки (після відповіді сторінки, див. EditLead::mount). Якщо кроку ще немає —
 * питає Binotel історію дзвінків по номерах клієнта й бере ПЕРШИЙ вихідний дзвінок після створення заявки
 * (будь-який результат: це спроба зв'язку, час реакції менеджера). Знайшли — пишемо крок з часом дзвінка.
 * Не знайшли — запам'ятовуємо час перевірки (first_call_checked_at) і не питаємо частіше ніж раз на CHECK_EVERY хв.
 */
class FirstCallDetector
{
    public const STEP = 'first_call';
    public const CHECK_EVERY_MINUTES = 3;
    private const URL = 'https://api.binotel.com/api/4.0/stats/history-by-external-number.json';
    /** Binotel callType: 0 — вхідний, 1 — вихідний. */
    private const OUTGOING = '1';

    public static function check(Lead $lead): void
    {
        if ($lead->hasStep(self::STEP)) {
            return;
        }

        if ($lead->first_call_checked_at && Carbon::parse($lead->first_call_checked_at)->gt(now()->subMinutes(self::CHECK_EVERY_MINUTES))) {
            return;
        }

        $account = BinotelAccount::where('is_active', true)->first() ?? BinotelAccount::first();
        $numbers = self::numbers($lead);

        if (! $account || $numbers === []) {
            return;
        }

        try {
            $r = Http::timeout(15)->asJson()->post(self::URL, [
                'companyID' => $account->company_id,
                'key' => $account->api_key,
                'secret' => $account->api_secret,
                'externalNumbers' => $numbers,
            ]);

            if (! $r->successful() || ! in_array($r->json('status'), ['success'], true)) {
                // 103 «Not enough data» — по цих номерах дзвінків ще немає; решта кодів (106 тощо) — просто спробуємо пізніше.
                if ($r->json('code') !== 103 && $r->json('code') !== '103') {
                    Log::info('Binotel first-call check failed', ['lead' => $lead->id, 'code' => $r->json('code')]);

                    return;
                }
            }

            $from = self::createdAt($lead)->timestamp;
            $first = collect($r->json('callDetails') ?? [])
                ->filter(fn ($c) => is_array($c) && (string) ($c['callType'] ?? '') === self::OUTGOING && (int) ($c['startTime'] ?? 0) >= $from)
                ->sortBy(fn ($c) => (int) $c['startTime'])
                ->first();

            if ($first) {
                $who = $first['employeeData']['name'] ?? null;
                ProcessSteps::reach(
                    $lead,
                    self::STEP,
                    'auto',
                    null,
                    'Binotel: вихідний дзвінок'.($who ? ' ('.$who.')' : ''),
                    false,
                    Carbon::createFromTimestamp((int) $first['startTime']),
                );
            }

            DB::table('leads')->where('id', $lead->id)->update(['first_call_checked_at' => now()]);
        } catch (\Throwable $e) {
            Log::info('Binotel first-call check error', ['lead' => $lead->id, 'error' => $e->getMessage()]);
        }
    }

    /** Справжній момент створення заявки (legacy-заявки лежать у «настінному» часі старого сервера, нові — в UTC). */
    public static function createdAt(Lead $lead): Carbon
    {
        $raw = $lead->created_at->format('Y-m-d H:i:s');

        return $lead->legacy_id !== null
            ? Carbon::parse($raw, config('leads.legacy_timezone', 'Europe/Berlin'))
            : Carbon::parse($raw, 'UTC');
    }

    /** Номери клієнта у форматі Binotel: 0XXXXXXXXX (останні 9 цифр). @return list<string> */
    private static function numbers(Lead $lead): array
    {
        $c = $lead->client;

        return collect([$c?->phone, $c?->phone2, $c?->viber])
            ->map(fn ($p) => preg_replace('/\D/', '', (string) $p))
            ->filter(fn ($p) => strlen($p) >= 9)
            ->map(fn ($p) => '0'.substr($p, -9))
            ->unique()->values()->all();
    }
}
