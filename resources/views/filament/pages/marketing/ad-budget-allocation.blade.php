<x-filament-panels::page>
    <style>
        .ab-table { width: 100%; border-collapse: collapse; font-size: .85rem; }
        .ab-table th { text-align: left; font-size: .65rem; text-transform: uppercase; letter-spacing: .04em; opacity: .6; padding: .4rem .6rem; border-bottom: 1px solid rgba(127,127,127,.3); }
        .ab-table td { padding: .6rem; border-bottom: 1px solid rgba(127,127,127,.15); vertical-align: top; }
        .ab-num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .ab-up { color: var(--success-600); font-weight: 700; }
        .ab-down { color: var(--danger-600); font-weight: 700; }
        .ab-hold { opacity: .7; }
        .ab-muted { opacity: .55; font-size: .75rem; }
        .ab-badge { display: inline-block; padding: 0 .4rem; border-radius: 9999px; font-size: .65rem; background: rgba(127,127,127,.2); }
    </style>

    <x-filament::section compact>
        <div style="display:flex;flex-wrap:wrap;gap:.5rem 2rem;align-items:center;justify-content:space-between;font-size:.85rem">
            <div>
                Період: <strong>{{ \Carbon\Carbon::parse($data['from'])->format('d.m.Y') }} — {{ \Carbon\Carbon::parse($data['to'])->format('d.m.Y') }}</strong>
                <span class="ab-muted">(останні 14 днів не враховуються: замовлення з'являються із запізненням)</span><br>
                Дані Google оновлено: <strong>{{ $data['synced_at'] ? \Carbon\Carbon::parse($data['synced_at'])->format('d.m.Y H:i') : 'ще ні' }}</strong>
                <span class="ab-muted">(автоматично щодня о 04:10)</span>
            </div>
            <x-filament::button wire:click="refreshStats" size="sm" color="gray">Оновити з Google</x-filament::button>
        </div>
    </x-filament::section>

    @if (count($data['rows']) === 0)
        <x-filament::section>Даних поки немає. Натисніть «Оновити з Google».</x-filament::section>
    @else
        <x-filament::section>
            <div style="overflow-x:auto">
                <table class="ab-table">
                    <thead>
                        <tr>
                            <th>Кампанія</th>
                            <th class="ab-num">Частка витрат</th>
                            <th class="ab-num">Частка обороту</th>
                            <th class="ab-num">Віддача (оборот / витрати)</th>
                            <th class="ab-num">Запас росту</th>
                            <th class="ab-num">Рекомендована частка</th>
                            <th>Що робити</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($data['rows'] as $r)
                            <tr>
                                <td>
                                    <strong>{{ $r['name'] }}</strong><br>
                                    <span class="ab-muted">
                                        {{ $r['status'] === 'ENABLED' ? 'увімкнена' : ($r['status'] === 'PAUSED' ? 'на паузі' : $r['status']) }}
                                        @if ($r['last_spend']) · остання витрата {{ \Carbon\Carbon::parse($r['last_spend'])->format('d.m') }} @endif
                                    </span>
                                    @if ($r['mapped_unconfirmed']) <br><span class="ab-badge">зіставлення не підтверджено</span> @endif
                                    @if ($r['low_confidence']) <br><span class="ab-badge">мало даних (замовлень: {{ $r['orders'] }})</span> @endif
                                </td>
                                <td class="ab-num">{{ number_format($r['spend_share'], 0) }}%</td>
                                <td class="ab-num">{{ number_format($r['revenue_share'], 0) }}%</td>
                                <td class="ab-num">
                                    {{ number_format($r['roas'], 1) }}×
                                    <div class="ab-muted">середня {{ number_format($data['avg_roas'], 1) }}×</div>
                                </td>
                                <td class="ab-num">
                                    @if ($r['headroom_lost'] === null)
                                        <span class="ab-muted">невідомо</span>
                                    @else
                                        {{ number_format($r['headroom_lost'] * 100, 0) }}%
                                        <div class="ab-muted">показів втрачено через бюджет</div>
                                    @endif
                                </td>
                                <td class="ab-num"><strong>{{ number_format($r['rec_share'], 0) }}%</strong></td>
                                <td>
                                    @if ($r['action'] === 'up')
                                        <span class="ab-up">Збільшити +{{ number_format($r['delta'], 0) }} п.п.</span>
                                    @elseif ($r['action'] === 'down')
                                        <span class="ab-down">Зменшити {{ number_format($r['delta'], 0) }} п.п.</span>
                                    @else
                                        <span class="ab-hold">Тримати</span>
                                    @endif
                                    @if ($r['low_confidence']) <div class="ab-muted">мало замовлень: частку не змінюємо</div> @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="ab-muted" style="margin-top:.8rem">
                Як читати: «частка витрат» — скільки від усіх витрат на рекламу пішло на кампанію; «частка обороту» — скільки обороту дали клієнти з її заявок.
                Рекомендована частка пропорційна віддачі (зі згладжуванням для малих вибірок) і не перевищує того, що кампанія може «освоїти» (за втраченими через бюджет показами, лише для пошуку).
                Це підказка, не наказ: вирішує агентство. Оцінювати зміни варто через 4–6 тижнів.
            </p>
        </x-filament::section>
    @endif

    <x-filament::section heading="Зіставлення назв UTM з кампаніями Google" compact>
        @if ($data['unmapped']->count())
            <p class="ab-muted" style="margin-bottom:.5rem">Ці UTM-назви є в заявках, але не прив'язані до кампанії Google, тож їхній оборот у таблиці не враховано.</p>
            <table class="ab-table" style="margin-bottom:1rem">
                @foreach ($data['unmapped'] as $u)
                    <tr>
                        <td>{{ $u->utm }} <span class="ab-muted">({{ $u->leads }} заявок)</span></td>
                        <td>
                            <select wire:model="pick.{{ $u->utm }}" class="block rounded-lg border border-gray-300 bg-white px-2 py-1 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                                <option value="">— обрати кампанію —</option>
                                @foreach ($data['campaigns'] as $c)
                                    <option value="{{ $c->google_campaign_id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td><x-filament::button size="xs" wire:click="mapCampaign(@js($u->utm))">Зберегти</x-filament::button></td>
                    </tr>
                @endforeach
            </table>
        @endif

        <table class="ab-table">
            @foreach ($data['mappings'] as $m)
                <tr>
                    <td>{{ $m->utm_campaign }}</td>
                    <td>→ {{ $data['campaigns'][$m->google_campaign_id]->name ?? $m->google_campaign_id }}</td>
                    <td>
                        @if ($m->confirmed)
                            <span class="ab-badge">підтверджено</span>
                        @else
                            <x-filament::button size="xs" color="success" wire:click="confirmMapping({{ $m->id }})">Підтвердити</x-filament::button>
                        @endif
                        <x-filament::button size="xs" color="gray" wire:click="removeMapping({{ $m->id }})" wire:confirm="Видалити зіставлення?">Видалити</x-filament::button>
                    </td>
                </tr>
            @endforeach
        </table>
    </x-filament::section>
</x-filament-panels::page>
