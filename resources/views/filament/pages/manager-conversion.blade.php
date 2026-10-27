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
        .mc-grid { display: grid; grid-template-columns: 1fr; gap: 1rem; }
        @media (min-width: 640px)  { .mc-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (min-width: 1280px) { .mc-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
        .mc-name { font-size: .95rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .mc-metrics { display: grid; grid-template-columns: 1fr 1fr; gap: .6rem .75rem; margin-top: .6rem; }
        .mc-label { font-size: .65rem; text-transform: uppercase; letter-spacing: .04em; opacity: .6; }
        .mc-value { font-size: 1.25rem; font-weight: 700; line-height: 1.3; white-space: nowrap; }
        .mc-result { display:flex; justify-content:space-between; align-items:flex-end; gap:.5rem; margin-top:.7rem; padding-top:.6rem; border-top:1px solid rgba(127,127,127,.25); }
        .mc-sub { font-size:.95rem; font-weight:600; white-space:nowrap; opacity:.8; }
        .mc-bar { height: 6px; border-radius: 9999px; background: rgba(127,127,127,.25); margin-top: .7rem; overflow: hidden; }
        .mc-bar > div { height: 100%; border-radius: 9999px; background: var(--primary-500); }
        .mc-foot { margin-top: .4rem; font-size: .7rem; opacity: .55; }
        .mc-good { color: var(--success-600); } .mc-mid { color: var(--warning-600); } .mc-bad { color: var(--danger-600); }
    </style>

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
                <x-filament::section compact>
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
                            <div class="mc-label">Реклама: факт / ліміт {{ $budgetPercent }}%</div>
                            <div class="mc-sub">{{ number_format($m['ad_cost'], 0, '.', ' ') }} / {{ number_format($m['ad_budget'], 0, '.', ' ') }} ₴</div>
                        </div>
                        <div style="text-align:right">
                            <div class="mc-label">Результат</div>
                            <div class="mc-value {{ $m['result'] >= 0 ? 'mc-good' : 'mc-bad' }}">{{ $m['result'] >= 0 ? '+' : '−' }}{{ number_format(abs($m['result']), 0, '.', ' ') }} ₴</div>
                        </div>
                    </div>

                    <div class="mc-bar"><div style="width: {{ min(100, $m['rate']) }}%"></div></div>
                    <div class="mc-foot">ДРР: <strong class="{{ ($m['drr'] ?? 0) > $budgetPercent ? 'mc-bad' : 'mc-good' }}">{{ $m['drr'] === null ? '—' : number_format($m['drr'], 1, '.', ' ').'%' }}</strong> · замовлень: {{ number_format($m['orders'], 0, '.', ' ') }}</div>
                </x-filament::section>
            @endforeach
        </div>
    @endif

</x-filament-panels::page>
