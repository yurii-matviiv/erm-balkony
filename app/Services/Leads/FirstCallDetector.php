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
    /** Крок «Контакт»: клієнт ВІДПОВІВ на вихідний дзвінок (Binotel disposition = ANSWER). */
    public const CONTACT_STEP = 'contacted';
    public const CHECK_EVERY_MINUTES = 3;
    private const URL = 'https://api.binotel.com/api/4.0/stats/history-by-external-number.json';
    /** Binotel callType: 0 — вхідний, 1 — вихідний. */
    private const OUTGOING = '1';

    public static function check(Lead $lead): void
    {
        // Ознака «заявка з сайту» не потребує Binotel — ставимо одразу.
        self::syncSourceFromSite($lead);

        // Питати Binotel далі треба, поки немає першого дзвінка, або немає контакту (клієнт ще не відповів), або спосіб звернення не визначено.
        if ($lead->hasStep(self::STEP) && $lead->hasStep(self::CONTACT_STEP) && ! self::sourceNeedsBinotel($lead)) {
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

            // Шукаємо з ПОЧАТКУ дня подання заявки: менеджер міг подзвонити клієнту до того, як заявку створили в системі.
            $from = self::createdAt($lead)->startOfDay()->timestamp;
            $calls = collect($r->json('callDetails') ?? [])->filter(fn ($c) => is_array($c));

            // Спосіб звернення «Дзвінок»: клієнт дзвонив у день створення (до створення або одразу після), а заявка не з сайту.
            if (self::sourceNeedsBinotel($lead)) {
                $till = self::createdAt($lead)->copy()->addHour()->timestamp;
                $hasIncoming = $calls->contains(fn ($c) => (string) ($c['callType'] ?? '') === '0'
                    && (int) ($c['startTime'] ?? 0) >= $from && (int) ($c['startTime'] ?? 0) <= $till);

                if ($hasIncoming) {
                    self::setSource($lead, 'call', 'є вхідний дзвінок Binotel, заявка не з сайту');
                }
            }

            $outgoing = $calls
                ->filter(fn ($c) => (string) ($c['callType'] ?? '') === self::OUTGOING && (int) ($c['startTime'] ?? 0) >= $from)
                ->sortBy(fn ($c) => (int) $c['startTime'])
                ->values();
            $first = $outgoing->first();
            DB::table('leads')->where('id', $lead->id)->update(['call_attempts' => min(255, $outgoing->count())]);
            $answered = $outgoing->first(fn ($c) => ($c['disposition'] ?? '') === 'ANSWER');
            $who = fn ($c) => ($c['employeeData']['name'] ?? null) ? ' ('.$c['employeeData']['name'].')' : '';

            // Перша СПРОБА (будь-який результат) — для швидкості реакції.
            if ($first && ! $lead->hasStep(self::STEP)) {
                ProcessSteps::reach($lead, self::STEP, 'auto', null,
                    'Binotel: вихідний дзвінок'.$who($first).(($first['disposition'] ?? '') === 'ANSWER' ? ', клієнт відповів' : ', клієнт не відповів'),
                    false, Carbon::createFromTimestamp((int) $first['startTime']));
            }

            // КОНТАКТ — перший дзвінок, на який клієнт відповів; рухає етап вперед (тільки вперед).
            if ($answered && ! $lead->hasStep(self::CONTACT_STEP)) {
                ProcessSteps::reach($lead, self::CONTACT_STEP, 'auto', null,
                    'Binotel: клієнт відповів'.$who($answered).', розмова '.(int) ($answered['billsec'] ?? 0).' с',
                    true, Carbon::createFromTimestamp((int) $answered['startTime']));
            }

            DB::table('leads')->where('id', $lead->id)->update(['first_call_checked_at' => now()]);
        } catch (\Throwable $e) {
            Log::info('Binotel first-call check error', ['lead' => $lead->id, 'error' => $e->getMessage()]);
        }
    }

    /** Форма на сайті залишила слід (form_name або ip) — це заявка з сайту. */
    private static function hasSiteMarker(Lead $lead): bool
    {
        $m = $lead->marketingData;

        return $m && (filled($m->form_name) || filled($m->ip_address));
    }

    /** «Дзвінок» ставимо лише там, де спосіб не вказано/невідомий/«з сайту» без сліду форми; явні вибори (візит, рекомендація, чати, Fb) не чіпаємо. */
    private static function sourceNeedsBinotel(Lead $lead): bool
    {
        return ! self::hasSiteMarker($lead) && in_array($lead->source, [null, '', 'unknown', 'site'], true);
    }

    private static function syncSourceFromSite(Lead $lead): void
    {
        if (self::hasSiteMarker($lead) && in_array($lead->source, [null, '', 'unknown', 'call'], true)) {
            self::setSource($lead, 'site', 'є слід форми на сайті');
        }
    }

    private static function setSource(Lead $lead, string $source, string $why): void
    {
        if ($lead->source === $source) {
            return;
        }

        DB::table('leads')->where('id', $lead->id)->update(['source' => $source]);
        Log::info('Lead source auto-set', ['lead' => $lead->id, 'from' => $lead->source, 'to' => $source, 'why' => $why]);
        $lead->source = $source;
        $lead->syncOriginalAttribute('source');
    }

    /** Хвилини від створення заявки до першого дзвінка; дзвінок до створення = 0 (рішення користувача). */
    public static function reactionMinutes(Lead $lead, \DateTimeInterface $firstCallAt): int
    {
        $secs = self::createdAt($lead)->diffInSeconds(Carbon::instance($firstCallAt), false);

        return max(0, (int) floor($secs / 60));
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
