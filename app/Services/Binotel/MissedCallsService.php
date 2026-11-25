<?php

namespace App\Services\Binotel;

use App\Models\BinotelAccount;
use App\Models\Client;
use App\Models\Lead;
use App\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Незакриті пропущені дзвінки (docs/modules/missed-calls.md).
 *
 * Пропущений = вхідний дзвінок, який не завершився ANSWER (SMS не рахуємо).
 * Закритий = після нього на цей номер був вихідний дзвінок (будь-який) або прийнятий вхідний від цього номера.
 * Вікно: з початку попереднього робочого дня (пн–пт) до зараз. Лише читання з Binotel, у Binotel нічого не пишемо.
 */
class MissedCallsService
{
    private const API = 'https://api.binotel.com/api/4.0/';

    /** Початок вікна: попередній робочий день 00:00 (у понеділок це п'ятниця). */
    public function windowStart(CarbonImmutable $now): CarbonImmutable
    {
        $d = $now->startOfDay()->subDay();
        while ($d->isWeekend()) {
            $d = $d->subDay();
        }

        return $d;
    }

    /**
     * @return array<int, array<string, mixed>> незакриті дзвінки, згруповані за номером клієнта (найновіші першими)
     */
    public function unresolved(CarbonImmutable $now): array
    {
        $start = $this->windowStart($now)->getTimestamp();
        $end = $now->getTimestamp();

        $incoming = collect($this->api('stats/incoming-calls-for-period', ['startTime' => $start, 'stopTime' => $end])['callDetails'] ?? [])
            ->filter(fn ($c) => ($c['callType'] ?? '') === '0')
            ->sortBy('startTime')
            ->values();
        sleep(2); // Binotel: помилка 106 при частих запитах
        $outgoing = collect($this->api('stats/outgoing-calls-for-period', ['startTime' => $start, 'stopTime' => $end])['callDetails'] ?? []);

        $outByNumber = $outgoing->groupBy(fn ($c) => $this->key($c['externalNumber'] ?? ''));
        $answeredByNumber = $incoming->filter(fn ($c) => ($c['disposition'] ?? '') === 'ANSWER')
            ->groupBy(fn ($c) => $this->key($c['externalNumber'] ?? ''));

        $groups = [];
        foreach ($incoming as $c) {
            $disposition = (string) ($c['disposition'] ?? '');
            if ($disposition === 'ANSWER' || str_starts_with($disposition, 'SMS')) {
                continue;
            }
            $key = $this->key($c['externalNumber'] ?? '');
            if (strlen($key) < 9) {
                continue; // не телефонний номер (наприклад, SMS-відправник)
            }
            $t = (int) $c['startTime'];
            $closed = ($outByNumber[$key] ?? collect())->contains(fn ($o) => (int) $o['startTime'] > $t)
                || ($answeredByNumber[$key] ?? collect())->contains(fn ($i) => (int) $i['startTime'] > $t);
            if ($closed) {
                continue;
            }
            $groups[$key]['calls'][] = $c;
        }

        $result = [];
        foreach ($groups as $key => $g) {
            $calls = $g['calls'];
            $last = end($calls);
            $result[] = [
                'key' => $key,
                'phone' => '0'.$key,
                'count' => count($calls),
                'last_time' => CarbonImmutable::createFromTimestamp((int) $last['startTime'], config('missed_calls.timezone')),
                'disposition' => (string) $last['disposition'],
                'wait' => (int) ($last['waitsec'] ?? 0),
                'binotel_name' => (string) ($last['customerData']['name'] ?? ''),
                'called_number' => (string) ($last['pbxNumberData']['number'] ?? ''),
                'called_name' => (string) ($last['pbxNumberData']['name'] ?? ''),
            ];
        }
        usort($result, fn ($a, $b) => $b['last_time']->getTimestamp() <=> $a['last_time']->getTimestamp());

        return $this->enrich($result);
    }

    /** Додає мітки Binotel та дані CRM; прибирає дзвінки з ignore_labels. */
    private function enrich(array $items): array
    {
        $ignore = config('missed_calls.ignore_labels', []);
        $out = [];
        foreach ($items as $item) {
            $item['labels'] = $this->labels($item['phone']);
            if ($ignore && array_intersect($ignore, array_map('mb_strtolower', $item['labels'] ?? []))) {
                continue;
            }
            $client = $this->findClient($item['key']);
            $item['client'] = $client;
            $item['lead'] = $client ? Lead::where('client_id', $client->id)->orderByDesc('id')->first() : null;
            $item['order'] = $client ? Order::where('client_id', $client->id)->orderByDesc('id')->first() : null;
            $out[] = $item;
        }

        return $out;
    }

    /** @return array<int,string>|null null = не вдалося отримати мітки */
    private function labels(string $phone): ?array
    {
        try {
            sleep(1);
            $data = $this->api('customers/search', ['subject' => $phone])['customerData'] ?? [];
            $names = [];
            foreach ($data as $customer) {
                foreach ($customer['labels'] ?? [] as $label) {
                    $names[] = (string) ($label['name'] ?? '');
                }
            }

            return array_values(array_filter($names));
        } catch (\Throwable $e) {
            Log::warning('MissedCalls: мітки Binotel недоступні', ['error' => $e::class]);

            return null;
        }
    }

    private function findClient(string $key): ?Client
    {
        $norm = fn (string $col) => "REPLACE(REPLACE(REPLACE(REPLACE($col,' ',''),'-',''),'(',''),')','') LIKE ?";

        return Client::query()
            ->where(fn ($q) => $q->whereRaw($norm('phone'), ["%$key"])
                ->orWhereRaw($norm('phone2'), ["%$key"])
                ->orWhereRaw($norm('viber'), ["%$key"]))
            ->orderByDesc('id')
            ->first();
    }

    /** Текст для Telegram (HTML). Порожній рядок, якщо нічого показувати. */
    public function message(array $items, CarbonImmutable $now): string
    {
        if (! $items) {
            return '';
        }
        $e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $base = config('missed_calls.erm_url');
        $lines = ['📵 <b>Незакриті пропущені дзвінки: '.count($items).'</b>', 'Перевірка '.$now->setTimezone(config('missed_calls.timezone'))->format('d.m H:i'), ''];

        foreach ($items as $i) {
            $client = $i['client'];
            $name = $client
                ? trim(implode(' ', array_filter([$client->last_name, $client->first_name, $client->middle_name])))
                : '';
            $name = $name !== '' ? $name : ($i['binotel_name'] !== '' ? $i['binotel_name'] : 'Без імені');

            $lines[] = '<b>'.$e($name).'</b>';
            $lines[] = '📱 '.$e($i['phone']);
            $when = $i['last_time']->format('d.m').' о '.$i['last_time']->format('H:i');
            $lines[] = $i['count'] > 1 ? '🕐 Дзвінків: '.$i['count'].', останній '.$when : '🕐 Дзвонив '.$when;
            if ($i['called_number'] !== '') {
                $lines[] = '☎️ На номер: '.$e($i['called_number']).($i['called_name'] !== '' ? ' ('.$e($i['called_name']).')' : '');
            }
            $lines[] = $this->dispositionText($i['disposition'], $i['wait']);
            $lines[] = '⚠️ Передзвону не було';

            if ($i['lead']) {
                $lead = $i['lead'];
                $lines[] = '✅ <b>Внесений у CRM:</b> <a href="'.$e("$base/admin/leads/{$lead->id}/edit").'">заявка №'.$lead->id.'</a>, '.$e($this->leadStatus($lead));
                if ($i['order']) {
                    $lines[] = '📦 <b>Замовлення:</b> <a href="'.$e("$base/admin/orders/{$i['order']->id}/edit").'">№'.$i['order']->id.'</a>, '.$e($this->orderStage($i['order']));
                }
            } elseif ($client) {
                $lines[] = '✅ <b>Клієнт є в CRM</b>, заявок немає';
            } else {
                $lines[] = '❌ <b>Не внесений у CRM</b>';
            }

            $manager = $i['order']?->manager ?? $i['lead']?->manager;
            $lines[] = '👤 <b>Відповідальний менеджер:</b> '.($manager ? $e($manager->name) : 'немає');

            $lines[] = '🏷 Мітки Binotel: '.($i['labels'] === null ? 'недоступні' : ($i['labels'] ? $e(implode(', ', $i['labels'])) : 'немає'));
            $lines[] = '🔗 <a href="https://my.binotel.ua/?module=history&amp;subject='.$e($i['phone']).'">Історія в Binotel</a>';
            $lines[] = '';
        }

        return rtrim(implode("\n", $lines));
    }

    private function dispositionText(string $d, int $wait): string
    {
        $text = match ($d) {
            'CANCEL' => '❌ Клієнт скинув слухавку',
            'VM' => '📼 Потрапив на автовідповідач',
            'VM-SUCCESS' => '📼 Потрапив на голосову пошту',
            'NOANSWER' => '🔕 Ніхто не відповів',
            'BUSY' => '⏳ Було зайнято',
            default => '❔ Статус: '.$d,
        };

        return $text.' (очікував '.$wait.' с)';
    }

    private function leadStatus(Lead $lead): string
    {
        return match ((string) $lead->status) {
            'won' => 'виграна',
            'lost' => 'програна',
            'open' => 'відкрита',
            default => (string) $lead->status,
        };
    }

    private function orderStage(Order $order): string
    {
        return match ((string) $order->stage) {
            'in_work' => 'в роботі',
            default => (string) $order->stage,
        };
    }

    /** Останні 9 цифр: 0677975536, +380677975536, 380677975536 → 677975536. */
    private function key(string $number): string
    {
        $digits = preg_replace('/\D/', '', $number);

        return strlen($digits) >= 9 ? substr($digits, -9) : '';
    }

    /** POST до Binotel з повтором на таймаут і помилку 106 (занадто часто). */
    private function api(string $method, array $payload): array
    {
        $account = BinotelAccount::query()->where('is_active', true)->first() ?? BinotelAccount::query()->first();
        if (! $account) {
            throw new \RuntimeException('Немає акаунта Binotel');
        }

        $last = null;
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $json = Http::timeout(30)->post(self::API.$method.'.json', array_merge(
                    ['key' => $account->api_key, 'secret' => $account->api_secret],
                    $payload,
                ))->json();
                if (($json['status'] ?? '') === 'success') {
                    return $json;
                }
                $last = 'code '.($json['code'] ?? '?');
                sleep(5);
            } catch (\Throwable $e) {
                $last = $e::class;
                sleep(3);
            }
        }

        throw new \RuntimeException("Binotel {$method}: {$last}");
    }
}
