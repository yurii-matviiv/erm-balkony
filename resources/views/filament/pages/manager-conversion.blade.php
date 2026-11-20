<x-filament-panels::page>

    {{-- Quiet freshness note, pulled up into the top-right corner of the page header (same line as the title).
         Zero-height wrapper + negative bottom margin (= the page's 2rem flex gap), so it takes no space and does not push the filter down.
         Numbers are cached, see ManagerStatsService::CACHE_MINUTES. --}}
    <div class="mc-fresh-wrap">
        <div class="mc-fresh">
            Дані оновлено о {{ \Carbon\Carbon::createFromTimestamp($computedAt)->timezone('Europe/Kyiv')->format('H:i') }} · оновлюються не частіше ніж раз на {{ \App\Services\ManagerConversion\ManagerStatsService::CACHE_MINUTES }} хв
        </div>
    </div>

    {{-- Date filter drives most of the page, so it stays pinned at the top while scrolling. --}}
    <div style="position:sticky;top:.5rem;z-index:30;box-shadow:0 6px 16px rgba(0,0,0,.12);border-radius:.75rem">
    <x-filament::section compact>
        <div class="flex flex-col gap-3">
            <div class="flex flex-wrap items-end gap-4">
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $compare ? 'Період 1' : 'Період' }}</label>
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
                <label class="flex items-center gap-2 pb-2 text-sm" style="cursor:pointer">
                    <input type="checkbox" wire:model.live="compare" class="fi-checkbox-input" />
                    Порівняти періоди
                </label>
                @unless ($compare)
                    @include('filament.pages.manager-conversion-picker', ['compare' => false, 'managerOptions' => $managerOptions, 'selectedManagers' => $selectedManagers, 'compareData' => null])
                @endunless
            </div>

            @if ($compare)
                <div class="flex flex-wrap items-end gap-4">
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-medium text-gray-500 dark:text-gray-400">Період 2</label>
                        <select wire:change="applyPreset2($event.target.value)"
                                class="block rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                            @foreach ($presetOptions as $value => $label)
                                <option value="{{ $value }}" @selected($preset2 === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-medium text-gray-500 dark:text-gray-400">З</label>
                        <input type="date" wire:model.live="dateFrom2" wire:change="dateChanged2" value="{{ $dateFrom2 }}"
                               class="block rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-medium text-gray-500 dark:text-gray-400">По</label>
                        <input type="date" wire:model.live="dateTo2" wire:change="dateChanged2" value="{{ $dateTo2 }}"
                               class="block rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200" />
                    </div>
                    @include('filament.pages.manager-conversion-picker', ['compare' => true, 'managerOptions' => $managerOptions, 'selectedManagers' => $selectedManagers, 'compareData' => $compareData])
                </div>
            @endif
        </div>
    </x-filament::section>
    </div>

    @unless ($compare)
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
    @endunless

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
        /* Tighter gap between the page title and the filter (Filament's default is 2rem). */
        .fi-page-header-main-ctn:has(.mc-fresh-wrap) { row-gap: .5rem !important; gap: .5rem !important; }
        .mc-fresh-wrap { position: relative; height: 0; margin-bottom: -2rem; }
        .mc-fresh { position: absolute; right: 0; top: -2.1rem; font-size: .7rem; color: rgb(156,163,175); white-space: nowrap; }
        @media (max-width: 767px) { .mc-fresh-wrap { height: auto; margin-bottom: 0; } .mc-fresh { position: static; white-space: normal; } }
        .mc-cmp-grid { display: grid; grid-template-columns: 1fr; gap: 1.25rem; }
        @media (min-width: 900px) { .mc-cmp-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        .mc-block { margin-top: 3rem; }
        .mc-block-plain { margin-bottom: 1.25rem; }
        .mc-block-head { display: flex; justify-content: space-between; align-items: center; gap: 1rem; margin-bottom: 1rem; padding: .35rem 0 .35rem 1rem; border-left: 6px solid var(--primary-500); }
        .mc-block-title { font-size: 1.6rem; font-weight: 800; line-height: 1.2; }
        .mc-block-desc { margin-top: .3rem; font-size: .9rem; max-width: 60rem; }
        .mc-foot { margin-top: .75rem; font-size: .7rem; opacity: .55; }
        .mc-good { color: var(--success-600); } .mc-mid { color: var(--warning-600); } .mc-bad { color: var(--danger-600); }
    </style>

    @if ($compare)
        @include('filament.pages.manager-conversion-compare', ['compareData' => $compareData, 'norm' => $norm, 'budgetPercent' => $budgetPercent, 'binotelKey' => $binotelKey, 'binotelKey2' => $binotelKey2])
    @else
    <div class="mc-block mc-block-plain">
        <div class="mc-block-head" style="margin-bottom:0">
            <div>
                <div class="mc-block-title">Конверсія менеджерів</div>
                <div class="mc-block-desc">
                    Розрахунок за вибраний угорі період:
                    скільки заявок отримав кожен менеджер, скільки з них стали замовленням (конверсія), оборот, витрати на рекламу й завантаження заявками.
                </div>
            </div>
        </div>
    </div>

    @if (count($managers) === 0)
        <x-filament::section>
            <div class="py-6 text-center text-sm text-gray-400">За цей період немає даних по менеджерах.</div>
        </x-filament::section>
    @else
        <div class="mc-grid">
            @foreach ($managers as $m)
                @include('filament.pages.manager-conversion-card', ['m' => $m, 'norm' => $norm, 'budgetPercent' => $budgetPercent])
            @endforeach
        </div>
    @endif

    @if ($trend)
        @include('filament.pages.manager-conversion-trend', ['trend' => $trend, 'trendName' => $trendName])
    @endif

    @if (count($recommendations) > 0)
        <style>
                        .mc-rec-cols { display: grid; grid-template-columns: minmax(0, 1.05fr) minmax(0, 1fr) minmax(0, 1.2fr) minmax(0, 1.15fr); gap: .5rem 1rem; align-items: start; }
            .mc-rec-source { padding: .6rem .9rem; border-left: 4px solid var(--primary-500); background: color-mix(in srgb, var(--primary-500) 14%, transparent); border-radius: .25rem; font-size: 1.05rem; font-weight: 700; }
            .mc-rec-source span { font-size: .85rem; font-weight: 600; margin-left: .5rem; }
            .mc-rec-head { padding: .6rem .9rem; font-weight: 700; font-size: .8rem; border-bottom: 2px solid rgba(127,127,127,.45); }
            .mc-rec-head small { display: block; font-size: .7rem; font-weight: 400; margin-top: .15rem; }
            .mc-rec-row { padding: .8rem .9rem; border-bottom: 1px solid rgba(127,127,127,.25); }
            .mc-rec-big { font-size: 1.5rem; font-weight: 700; line-height: 1.2; }
            .mc-rec-sub { font-size: .75rem; margin-top: .2rem; }
            .mc-rec-tip { cursor: help; display: block; }
            .mc-rec-note { font-size: .85rem; line-height: 1.5; margin-bottom: 1.25rem; }
        </style>

        <div class="mc-block">
        <div class="mc-block-head">
            <div>
                <div class="mc-block-title">Розподіл заявок між менеджерами — рекомендація</div>
                <div class="mc-block-desc">Кому з менеджерів скільки заявок віддавати. Окремо для дзвінків і для заявок із сайту (реклами). Не залежить від вибраного періоду.</div>
            </div>
            <div>{{ $this->recommendationInstructionAction }}</div>
        </div>
        <x-filament::section>

            <div class="mc-rec-note">
                @if (count($selectedManagers) > 0)
                    <strong>Показано лише: {{ implode(', ', $selectedManagers) }}.</strong> Частки розраховані серед усіх менеджерів, тому сума показаних рядків може бути не 100%.
                @endif
                <strong>Це підказка, а не наказ:</strong> система нічого не перерозподіляє.
                <strong>Навантаження</strong> тут — це частка заявок джерела, яку отримує менеджер.
                Рекомендація спирається на конверсію заявки в замовлення за {{ \App\Services\ManagerConversion\RecommendationService::REC_WINDOW_DAYS }} днів і на телефонне навантаження.
                Наведіть на рекомендоване число — побачите, чому воно таке.
            </div>

            {{-- One source at a time. Tabs switch client-side (Alpine, x-show): both sources are rendered once with the page,
                 so clicking a tab does NOT trigger a Livewire round-trip / recalculation. --}}
            <div x-data="{ tab: @js($recommendations[0]['key']) }">
            <x-filament::tabs style="margin-bottom:1rem">
                @foreach ($recommendations as $rec)
                    <x-filament::tabs.item
                        :alpine-active="'tab === \''.$rec['key'].'\''"
                        :badge="$rec['cur_total']"
                        x-on:click="tab = '{{ $rec['key'] }}'">
                        {{ $rec['title'] }}
                    </x-filament::tabs.item>
                @endforeach
            </x-filament::tabs>

            <div>
                @foreach ($recommendations as $rec)
                    <div x-show="tab === '{{ $rec['key'] }}'" @if (! $loop->first) x-cloak @endif>
                        <div class="mc-rec-source">
                            {{ $rec['title'] }}
                            <span>середня конверсія {{ number_format($rec['avg'], 1, ',', ' ') }}% · заявок за {{ \App\Services\ManagerConversion\RecommendationService::REC_CURRENT_DAYS }} днів: {{ $rec['cur_total'] }}</span>
                        </div>

                        <div class="mc-rec-cols mc-rec-head">
                            <div>Менеджер</div>
                            <div>Поточне навантаження<small>частка заявок за {{ \App\Services\ManagerConversion\RecommendationService::REC_CURRENT_DAYS }} днів</small></div>
                            <div>Рекомендоване навантаження<small>яку частку заявок віддавати</small></div>
                            <div>Конверсія (факт)<small>заявка → замовлення, {{ \App\Services\ManagerConversion\RecommendationService::REC_WINDOW_DAYS }} днів</small></div>
                        </div>

                        @foreach ($rec['rows'] as $r)
                            @php
                                $zero = $r['share'] === 0;
                                $delta = $r['delta'];
                                $noChange = $delta !== null && abs($delta) < \App\Services\ManagerConversion\RecommendationService::REC_NOCHANGE_PP;
                                $avgRate = $rec['avg'];
                                $lv = $r['phone'] ? ['high' => ['mc-bad', 'високе'], 'mid' => ['mc-mid', 'помірне'], 'low' => ['mc-good', 'є запас']][$r['phone']['level']] : null;
                                $pd = $r['phone'] ? number_format($r['phone']['per_day'], 1, ',', ' ') : null;
                                $cd = \App\Services\ManagerConversion\RecommendationService::REC_CURRENT_DAYS;
                                $wd = \App\Services\ManagerConversion\RecommendationService::REC_WINDOW_DAYS;

                                // Hover text: what the number means and why it is what it is.
                                $tip = '<strong>Це рекомендація, а не наказ.</strong><br>';
                                if ($r['cur_share'] === null) {
                                    $tip .= e($r['name']).': за останні '.$cd.' днів заявок із цього джерела не було. Рекомендовано '.$r['share'].'%.';
                                } elseif ($noChange) {
                                    $tip .= e($r['name']).': зараз '.$r['cur_share'].'% заявок ('.$r['cur_n'].' із '.$r['cur_total'].' за '.$cd.' днів), рекомендовано '.$r['share'].'%. Розподіл уже близький до рекомендованого — міняти не треба.';
                                } else {
                                    $tip .= e($r['name']).': зараз '.$r['cur_share'].'% заявок ('.$r['cur_n'].' із '.$r['cur_total'].' за '.$cd.' днів), рекомендовано '.$r['share'].'% — <strong>'.($delta > 0 ? 'збільшити' : 'зменшити').' на '.abs($delta).' п.п.</strong>';
                                }
                                $tip .= '<br><br>';
                                if ($r['rate'] === null) {
                                    $tip .= 'Чому: за '.$wd.' днів заявок із цього джерела не було, статистики немає.';
                                } elseif (! $r['reliable']) {
                                    $tip .= 'Чому: лише '.$r['n'].' заявок за '.$wd.' днів (потрібно від '.\App\Services\ManagerConversion\RecommendationService::REC_MIN_LEADS.'), статистика ненадійна — частку занижено, поки не набереться досвіду.';
                                } else {
                                    $tip .= 'Чому: конверсія '.number_format($r['rate'], 1, ',', ' ').'% ('.$r['conv'].' з '.$r['n'].' заявок), це '.($r['rate'] >= $avgRate ? 'вище' : 'нижче').' середньої '.number_format($avgRate, 1, ',', ' ').'%.';
                                }
                                if ($r['capped_from']) {
                                    $tip .= '<br>Частку зменшено з '.$r['capped_from'].'%: '.e($r['name']).' вже приймає '.$pd.' дзвінків/день (межа '.\App\Services\ManagerConversion\RecommendationService::PHONE_CALLS_HIGH.'), решту отримують інші.';
                                }
                                if ($zero && $r['rate'] !== null && $r['reliable']) {
                                    $tip .= '<br>Конверсія значно нижча за інших — із цього джерела заявки не віддавати.';
                                }
                            @endphp
                            <div class="mc-rec-cols mc-rec-row">
                                {{-- 1. Manager (+ phone calls for the call source) --}}
                                <div>
                                    <div style="font-weight:700">{{ $r['name'] }}</div>
                                    @if ($r['phone'])
                                        <div class="mc-rec-sub">☎ {{ $pd }} дзвінків/день · <strong class="{{ $lv[0] }}">{{ $lv[1] }}</strong></div>
                                    @endif
                                </div>

                                {{-- 2. Current load: actual share of the source's leads --}}
                                <div>
                                    @if ($r['cur_share'] === null)
                                        <div class="mc-rec-big">—</div>
                                        <div class="mc-rec-sub">заявок не було</div>
                                    @else
                                        <div class="mc-rec-big">{{ $r['cur_share'] }}%</div>
                                        <div class="mc-rec-sub">{{ $r['cur_n'] }} із {{ $r['cur_total'] }} заявок</div>
                                    @endif
                                </div>

                                {{-- 3. Recommended load --}}
                                <div>
                                    <span class="mc-rec-tip" tabindex="0" x-data x-tooltip="{ content: @js($tip), allowHTML: true, maxWidth: 360, theme: $store.theme }">
                                        <span class="mc-rec-big {{ $zero ? 'mc-bad' : '' }}">{{ $r['share'] }}%</span>
                                        <span class="mc-rec-sub" style="display:block">
                                            @if ($zero)
                                                <strong class="mc-bad">не віддавати</strong>
                                            @elseif ($delta === null || $noChange)
                                                <strong>без змін</strong>
                                            @elseif ($delta > 0)
                                                <strong class="mc-good">▲ збільшити на {{ $delta }} п.п.</strong>
                                            @else
                                                <strong class="mc-mid">▼ зменшити на {{ abs($delta) }} п.п.</strong>
                                            @endif
                                        </span>
                                        @if ($r['capped_from'])
                                            <span class="mc-rec-sub mc-mid" style="display:block">обмежено телефоном (без цього було б {{ $r['capped_from'] }}%)</span>
                                        @endif
                                    </span>
                                </div>

                                {{-- 4. Fact: conversion over the window --}}
                                <div>
                                    @if ($r['rate'] === null)
                                        <div class="mc-rec-sub">немає заявок за {{ $wd }} днів</div>
                                    @else
                                        <div class="mc-rec-big">{{ number_format($r['rate'], 1, ',', ' ') }}%</div>
                                        <div class="mc-rec-sub">{{ $r['conv'] }} з {{ $r['n'] }} заявок стали замовленням</div>
                                        @if ($r['reliable'])
                                            <div class="mc-rec-sub"><strong class="{{ $r['rate'] >= $avgRate ? 'mc-good' : 'mc-bad' }}">{{ $r['rate'] >= $avgRate ? '▲ вище середньої' : '▼ нижче середньої' }}</strong></div>
                                        @else
                                            <div class="mc-rec-sub mc-mid"><strong>⚠ мало даних</strong> (менше {{ \App\Services\ManagerConversion\RecommendationService::REC_MIN_LEADS }} заявок)</div>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        @endforeach

                        @if (($rec['unassigned'] ?? 0) > 0)
                            <div class="mc-rec-sub mc-mid" style="margin-top:.6rem">Не розподілено {{ $rec['unassigned'] }}% — усі вже на межі телефонного навантаження</div>
                        @endif
                    </div>
                @endforeach
            </div>

            </div>

            <div style="margin-top:1.5rem;padding-top:1rem;border-top:2px solid rgba(127,127,127,.45);font-size:.9rem">
                <div style="font-weight:700;margin-bottom:.35rem">Заявок на день при рекомендованому розподілі (норма на людину — {{ $norm }})</div>
                @foreach ($expected as $e)
                    <span style="margin-right:1.4rem;white-space:nowrap">{{ $e['name'] }}
                        <strong class="{{ $e['over'] ? 'mc-bad' : '' }}">{{ number_format($e['per_day'], 1, ',', ' ') }}</strong>{{ $e['over'] ? ' — вище норми' : '' }}</span>
                @endforeach
            </div>
        </x-filament::section>
        </div>
    @endif

    <div class="mc-block">
    <div class="mc-block-head">
        <div>
            <div class="mc-block-title">Телефонне навантаження (Binotel)</div>
            <div class="mc-block-desc">Розмови менеджерів за вибраний угорі період: вхідні прийняті та вихідні з дозвоном.</div>
        </div>
    </div>
    <x-filament::section>
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
    </div>
    @endif

</x-filament-panels::page>
