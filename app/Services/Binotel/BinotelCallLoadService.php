<?php

namespace App\Services\Binotel;

use App\Models\BinotelAccount;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Phone workload per employee from the Binotel API (read-only).
 *
 * Returns raw per-employee call totals; mapping an employee to an ERM user
 * is the caller's job (Binotel only knows names like "Анна SIP").
 *
 * Binotel limits request frequency (error 106), so a period is fetched in
 * 14-day chunks with a pause, and every chunk is cached: finished chunks
 * for a day, the chunk containing today for 10 minutes.
 */
class BinotelCallLoadService
{
    private const BASE_URL = 'https://api.binotel.com/api/4.0';
    private const CHUNK_DAYS = 14;
    /** Longer periods would mean dozens of API calls on page load. */
    public const MAX_DAYS = 62;
    private const TZ = 'Europe/Kyiv';

    /**
     * @return array{ok:bool,error?:string,employees?:array<string,array{name:string,in_answered:int,in_talk_sec:int,out_answered:int,out_attempts:int,out_talk_sec:int}>}
     */
    public function forPeriod(string $from, string $to): array
    {
        $start = Carbon::parse($from, self::TZ)->startOfDay();
        $end = min(Carbon::parse($to, self::TZ)->endOfDay(), Carbon::now(self::TZ));

        if ($start->diffInDays($end) > self::MAX_DAYS) {
            return ['ok' => false, 'error' => 'Для Binotel оберіть період не довше '.self::MAX_DAYS.' днів.'];
        }

        if ($start > $end) {
            return ['ok' => true, 'employees' => []];
        }

        $account = BinotelAccount::where('is_active', true)->first() ?? BinotelAccount::first();

        if (! $account) {
            return ['ok' => false, 'error' => 'Binotel не підключено (Інтеграції → Binotel).'];
        }

        $employees = [];

        try {
            for ($cursor = $start->copy(); $cursor <= $end; $cursor->addDays(self::CHUNK_DAYS)) {
                $chunkEnd = min($cursor->copy()->addDays(self::CHUNK_DAYS)->subSecond(), $end);

                foreach (['incoming' => 'stats/incoming-calls-for-period', 'outgoing' => 'stats/outgoing-calls-for-period'] as $dir => $method) {
                    $calls = $this->chunk($account, $method, $dir, $cursor->timestamp, $chunkEnd->timestamp);

                    foreach ($calls as $c) {
                        $name = $c['employeeData']['name'] ?? null;
                        if (! $name) {
                            continue; // not handled by an employee (voicemail, IVR, SMS ...)
                        }

                        $e = &$employees[$name];
                        $e ??= ['name' => $name, 'in_answered' => 0, 'in_talk_sec' => 0, 'out_answered' => 0, 'out_attempts' => 0, 'out_talk_sec' => 0];

                        $answered = ($c['disposition'] ?? '') === 'ANSWER';
                        $sec = (int) ($c['billsec'] ?? 0);

                        if ($dir === 'incoming') {
                            if ($answered) {
                                $e['in_answered']++;
                                $e['in_talk_sec'] += $sec;
                            }
                        } else {
                            $e['out_attempts']++;
                            if ($answered) {
                                $e['out_answered']++;
                                $e['out_talk_sec'] += $sec;
                            }
                        }
                        unset($e);
                    }
                }
            }
        } catch (\Throwable $ex) {
            Log::warning('Binotel call load failed', ['error' => $ex->getMessage()]);

            return ['ok' => false, 'error' => 'Binotel зараз недоступний: '.$ex->getMessage()];
        }

        return ['ok' => true, 'employees' => $employees];
    }

    /** @return list<array<string,mixed>> */
    private function chunk(BinotelAccount $account, string $method, string $dir, int $startTs, int $stopTs): array
    {
        $finished = $stopTs < now()->subHour()->timestamp;

        return Cache::remember(
            "binotel:calls:{$dir}:{$startTs}:{$stopTs}",
            $finished ? now()->addDay() : now()->addMinutes(10),
            function () use ($account, $method, $startTs, $stopTs): array {
                for ($try = 0; $try < 3; $try++) {
                    $r = Http::timeout(40)->asJson()->post(self::BASE_URL.'/'.$method.'.json', [
                        'companyID' => $account->company_id,
                        'key' => $account->api_key,
                        'secret' => $account->api_secret,
                        'startTime' => $startTs,
                        'stopTime' => $stopTs,
                    ]);

                    if ($r->successful() && $r->json('status') === 'success') {
                        sleep(1); // stay under Binotel's request-frequency limit

                        return array_values($r->json('callDetails') ?? []);
                    }

                    // 106 = "Requests are too frequent": wait and retry.
                    sleep(4);
                }

                throw new \RuntimeException('API відповів помилкою '.($r->json('code') ?? $r->status()));
            }
        );
    }
}
