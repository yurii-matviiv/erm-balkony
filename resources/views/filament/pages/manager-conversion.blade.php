<x-filament-panels::page>

    <x-filament::section>
        <div class="flex flex-wrap items-end gap-4">
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-500 dark:text-gray-400">Період</label>
                <select wire:change="applyPreset($event.target.value)"
                        class="block rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                    @foreach ($presetOptions as $value => $label)
                        <option value="{{ $value }}" @selected($preset === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-500 dark:text-gray-400">З</label>
                <input type="date" wire:model.live="dateFrom" wire:change="dateChanged" value="{{ $dateFrom }}"
                       class="block rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-500 dark:text-gray-400">По</label>
                <input type="date" wire:model.live="dateTo" wire:change="dateChanged" value="{{ $dateTo }}"
                       class="block rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200" />
            </div>
            <div class="flex items-center pb-1 text-sm text-gray-400 dark:text-gray-500">
                {{ \Carbon\Carbon::parse($dateFrom)->format('d.m.Y') }} — {{ \Carbon\Carbon::parse($dateTo)->format('d.m.Y') }}
            </div>
        </div>
    </x-filament::section>

    <x-filament::section compact>
        <div style="display:flex;flex-wrap:wrap;gap:.5rem 2rem;font-size:.85rem">
            <div>Реклама за період: <strong>{{ number_format($adSpend, 0, '.', ' ') }} ₴</strong></div>
            <div>Заявок усього: <strong>{{ number_format($totalLeads, 0, '.', ' ') }}</strong></div>
            <div>Середня ціна заявки:
                <strong style="color: var({{ $costPerLead > 550 ? '--danger-600' : '--success-600' }})">{{ number_format($costPerLead, 0, '.', ' ') }} ₴</strong>
                <span style="opacity:.55">(орієнтир до 550 ₴)</span>
            </div>
        </div>
    </x-filament::section>

    {{-- Own CSS (not Tailwind utilities): new utility classes would need `npm run build`, and the
         built assets are what gets deployed. 4 cards per row on wide screens, 2 on medium, 1 on mobile. --}}
    <style>
        .mc-grid { display: grid; grid-template-columns: 1fr; gap: 1.25rem; }
        @media (min-width: 640px)  { .mc-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (min-width: 1280px) { .mc-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
        .mc-name { font-size: .95rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .mc-metrics { display: grid; grid-template-columns: 1fr 1fr; gap: 1.1rem 1rem; margin-top: 1rem; }
        .mc-label { margin-bottom: .2rem; font-size: .65rem; text-transform: uppercase; letter-spacing: .04em; opacity: .6; }
        .mc-value { font-size: 1.25rem; font-weight: 700; line-height: 1.3; white-space: nowrap; }
        .mc-result { display:flex; justify-content:space-between; align-items:flex-end; gap:.5rem; margin-top:1.25rem; padding-top:1.1rem; border-top:1px solid rgba(127,127,127,.25); }
        .mc-sub { font-size:.95rem; font-weight:600; white-space:nowrap; opacity:.8; }
        .mc-load { margin-top:1.25rem; padding-top:1.1rem; border-top:1px solid rgba(127,127,127,.25); }
        .mc-load-head { display:flex; justify-content:space-between; align-items:baseline; gap:.5rem; font-size:.8rem; }
        .mc-load .mc-bar { margin-top:.6rem; }
        .mc-low { color: var(--info-600, #2563eb); } .mc-bar-low > div { background: var(--info-500, #3b82f6); }
        .mc-bar-ok > div { background: var(--success-500); }
        .mc-bar-full > div { background: var(--warning-500); }
        .mc-bar-over > div { background: var(--danger-500); }
        .mc-bar { height: 6px; border-radius: 9999px; background: rgba(127,127,127,.25); margin-top: 1.1rem; overflow: hidden; }
        .mc-bar > div { height: 100%; border-radius: 9999px; background: var(--primary-500); }
        .mc-foot { margin-top: .75rem; font-size: .7rem; opacity: .55; }
        .mc-good { color: var(--success-600); } .mc-mid { color: var(--warning-600); } .mc-bad { color: var(--danger-600); }
    </style>

    @if (count($recommendations) > 0)
        <x-filament::section heading="Кого на що ставити" description="Рекомендований розподіл заявок за джерелом, на основі конверсії заявка → замовлення (90 днів без останніх 14)">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:1.5rem">
                @foreach ($recommendations as $rec)
                    <div>
                        <div class="mc-label" style="margin-bottom:.6rem">
                            {{ $rec['title'] }} · середня конверсія {{ number_format($rec['avg'], 1, ',', ' ') }}%
                        </div>
                        @foreach ($rec['rows'] as $r)
                            <div style="display:flex;justify-content:space-between;align-items:baseline;gap:1rem;padding:.5rem 0;font-size:.875rem;border-top:1px solid rgba(127,127,127,.2);{{ $r['share'] === 0 ? 'opacity:.6' : '' }}">
                                <span>
                                    <strong style="font-size:1.05rem" class="{{ $r['share'] === 0 ? 'mc-bad' : '' }}">{{ $r['share'] }}%</strong>
                                    &nbsp;{{ $r['name'] }}
                                    @if ($r['phone'])
                                        @php
                                            $lv = ['high' => ['mc-bad', 'високе'], 'mid' => ['mc-mid', 'помірне'], 'low' => ['mc-good', 'є запас']][$r['phone']['level']];
                                        @endphp
                                        <span style="display:block;font-size:.75rem;margin-top:.15rem">
                                            ☎ {{ number_format($r['phone']['per_day'], 1, ',', ' ') }} дзвінків/день ·
                                            <strong class="{{ $lv[0] }}">{{ $lv[1] }}</strong>
                                            @if ($r['capped_from'])
                                                · <span class="mc-mid">обмежено телефоном (без обмеження було {{ $r['capped_from'] }}%)</span>
                                            @endif
                                        </span>
                                    @endif
                                </span>
                                <span style="white-space:nowrap;font-size:.8rem;opacity:.75">
                                    @if ($r['rate'] === null)
                                        немає заявок
                                    @else
                                        {{ number_format($r['rate'], 1, ',', ' ') }}% · {{ $r['conv'] }} з {{ $r['n'] }}{{ $r['reliable'] ? '' : ' · мало даних' }}
                                    @endif
                                </span>
                            </div>
                        @endforeach
                        @if (($rec['unassigned'] ?? 0) > 0)
                            <div class="mc-foot mc-mid">Не розподілено {{ $rec['unassigned'] }}% — усі вже на межі телефонного навантаження</div>
                        @endif
                    </div>
                @endforeach
            </div>

            <div style="margin-top:1.25rem;padding-top:1rem;border-top:1px solid rgba(127,127,127,.2);font-size:.8rem">
                <span style="opacity:.6">Очікується заявок на день при такому розподілі (норма {{ $norm }}):</span>
                @foreach ($expected as $e)
                    <span style="margin-left:.9rem;white-space:nowrap">{{ $e['name'] }}
                        <strong class="{{ $e['over'] ? 'mc-bad' : '' }}">{{ number_format($e['per_day'], 1, ',', ' ') }}</strong></span>
                @endforeach
            </div>
        </x-filament::section>
    @endif

    <x-filament::section heading="Телефонне навантаження (Binotel)" description="Розмови менеджерів за вибраний період: вхідні прийняті та вихідні з дозвоном">
        <div wire:key="binotel-{{ $binotelKey }}" wire:init="loadBinotel">
            @if ($binotelState === null)
                <div style="font-size:.85rem;opacity:.6">Завантаження з Binotel… (телефонне навантаження в рекомендаціях зверху з'явиться після цього)</div>
            @elseif (! ($binotelState['ok'] ?? false))
                <div style="font-size:.85rem" class="mc-bad">{{ $binotelState['error'] ?? 'Не вдалося отримати дані Binotel' }}</div>
            @else
                @php
                    $dur = fn (int $s): string => intdiv($s, 3600).' год '.str_pad((string) intdiv($s % 3600, 60), 2, '0', STR_PAD_LEFT).' хв';
                @endphp
                <div style="overflow-x:auto">
                    <table style="width:100%;font-size:.85rem;border-collapse:collapse">
                        <thead>
                            <tr class="mc-label" style="text-align:right">
                                <th style="text-align:left;padding:.4rem .5rem .6rem 0">Менеджер</th>
                                <th style="padding:.4rem .75rem .6rem">Вхідні прийнято</th>
                                <th style="padding:.4rem .75rem .6rem">Вихідні (дозвон / спроб)</th>
                                <th style="padding:.4rem .75rem .6rem">Час розмов</th>
                                <th style="padding:.4rem .75rem .6rem">Дзвінків на день</th>
                                <th style="padding:.4rem 0 .6rem .75rem">Годин розмов на день</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($phone as $r)
                                <tr style="border-top:1px solid rgba(127,127,127,.2);text-align:right">
                                    <td style="text-align:left;padding:.55rem .5rem .55rem 0">{{ $r['name'] }}</td>
                                    <td style="padding:.55rem .75rem">{{ $r['in'] }}</td>
                                    <td style="padding:.55rem .75rem">{{ $r['out'] }} / {{ $r['out_try'] }}</td>
                                    <td style="padding:.55rem .75rem">{{ $dur($r['sec']) }}</td>
                                    <td style="padding:.55rem .75rem"><strong>{{ number_format($r['calls_per_day'], 1, ',', ' ') }}</strong></td>
                                    <td style="padding:.55rem 0 .55rem .75rem"><strong>{{ number_format($r['hours_per_day'], 1, ',', ' ') }}</strong></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mc-foot" style="margin-top:.9rem">
                    Лише телефон: заявки із сайту й месенджерів тут не видно. Менеджера в Binotel шукаємо за ім'ям (Анна SIP → Анна), тому імена активних менеджерів мають бути різними. «На день» рахується за робочими днями (пн–пт).
                </div>
            @endif
        </div>
    </x-filament::section>

    @if (count($managers) === 0)
        <x-filament::section>
            <div class="py-6 text-center text-sm text-gray-400">За цей період немає даних по менеджерах.</div>
        </x-filament::section>
    @else
        <div class="mc-grid">
            @foreach ($managers as $m)
                @php
                    $rateClass = $m['rate'] >= 30 ? 'mc-good' : ($m['rate'] >= 15 ? 'mc-mid' : 'mc-bad');
                @endphp
                <x-filament::section>
                    <div class="mc-name" title="{{ $m['name'] }}">{{ $m['name'] }}</div>

                    <div class="mc-metrics">
                        <div>
                            <div class="mc-label">Заявок</div>
                            <div class="mc-value">{{ number_format($m['leads'], 0, '.', ' ') }}</div>
                        </div>
                        <div>
                            <div class="mc-label">В замовлення</div>
                            <div class="mc-value">{{ number_format($m['converted'], 0, '.', ' ') }}</div>
                        </div>
                        <div>
                            <div class="mc-label">Конверсія</div>
                            <div class="mc-value {{ $rateClass }}">{{ number_format($m['rate'], 1, '.', ' ') }}%</div>
                        </div>
                        <div>
                            <div class="mc-label">Оборот</div>
                            <div class="mc-value mc-good">{{ number_format($m['turnover'], 0, '.', ' ') }} ₴</div>
                        </div>
                    </div>

                    <div class="mc-result">
                        <div>
                            <div class="mc-label">Витрати на рекламу для менеджера</div>
                            <div class="mc-sub">{{ number_format($m['ad_cost'], 0, '.', ' ') }} / {{ number_format($m['ad_budget'], 0, '.', ' ') }} ₴</div>
                            <div style="font-size:.8rem;margin-top:.15rem">
                                <strong class="{{ ($m['drr'] ?? 0) > $budgetPercent ? 'mc-bad' : 'mc-good' }}">{{ $m['drr'] === null ? '—' : number_format($m['drr'], 1, ',', ' ').'%' }}</strong>
                                <span style="opacity:.6">від обороту</span>
                            </div>
                        </div>
                        <div style="text-align:right">
                            <div class="mc-label">Результат</div>
                            <div class="mc-value {{ $m['result'] >= 0 ? 'mc-good' : 'mc-bad' }}">{{ $m['result'] >= 0 ? '+' : '−' }}{{ number_format(abs($m['result']), 0, '.', ' ') }} ₴</div>
                        </div>
                    </div>

                    @php
                        $loadLabel = ['low' => 'Низьке · є запас', 'ok' => 'Нормальне', 'full' => 'На межі', 'over' => 'Перевантаження', 'none' => '—'][$m['load_level']];
                        $loadClass = ['low' => 'mc-low', 'ok' => 'mc-good', 'full' => 'mc-mid', 'over' => 'mc-bad', 'none' => ''][$m['load_level']];
                    @endphp
                    <div class="mc-load">
                        <div class="mc-label">Навантаження (заявок / робочий день)</div>
                        <div class="mc-load-head">
                            <span><strong>{{ $m['per_day'] === null ? '—' : number_format($m['per_day'], 1, ',', ' ') }}</strong> з {{ $norm }} · пік дня: {{ $m['peak'] }}</span>
                            <strong class="{{ $loadClass }}">{{ $loadLabel }}{{ $m['load'] !== null ? ' · '.$m['load'].'%' : '' }}</strong>
                        </div>
                        <div class="mc-bar mc-bar-{{ $m['load_level'] }}"><div style="width: {{ min(100, $m['load'] ?? 0) }}%"></div></div>
                    </div>

                    <div class="mc-bar"><div style="width: {{ min(100, $m['rate']) }}%"></div></div>
                    <div class="mc-foot">Замовлень за період: {{ number_format($m['orders'], 0, '.', ' ') }}</div>
                </x-filament::section>
            @endforeach
        </div>
    @endif

</x-filament-panels::page>
