<x-filament-panels::page>

    {{-- ═══════════════════════════════════════════════
         FILTER — single date range for the whole page
         ═══════════════════════════════════════════════ --}}
    <x-filament::section>
        <div class="flex flex-wrap items-end gap-4">

            {{-- Preset dropdown --}}
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-500 dark:text-gray-400">Період</label>
                <select
                    wire:change="applyPreset($event.target.value)"
                    class="block rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm
                           focus:border-primary-500 focus:ring-primary-500
                           dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                >
                    @foreach ($presetOptions as $value => $label)
                        <option value="{{ $value }}" @selected($preset === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            {{-- From date --}}
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-500 dark:text-gray-400">З</label>
                <input
                    type="date"
                    wire:model.live="dateFrom"
                    wire:change="dateChanged"
                    value="{{ $dateFrom }}"
                    class="block rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm
                           focus:border-primary-500 focus:ring-primary-500
                           dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                />
            </div>

            {{-- To date --}}
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-500 dark:text-gray-400">По</label>
                <input
                    type="date"
                    wire:model.live="dateTo"
                    wire:change="dateChanged"
                    value="{{ $dateTo }}"
                    class="block rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm
                           focus:border-primary-500 focus:ring-primary-500
                           dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                />
            </div>

            {{-- Active period label --}}
            <div class="flex items-center pb-1">
                <span class="text-sm text-gray-400 dark:text-gray-500">
                    {{ $presetLabel }}:
                    <span class="font-medium text-gray-700 dark:text-gray-300">
                        {{ \Carbon\Carbon::parse($dateFrom)->format('d.m.Y') }}
                        —
                        {{ \Carbon\Carbon::parse($dateTo)->format('d.m.Y') }}
                    </span>
                </span>
            </div>

        </div>
    </x-filament::section>

    {{-- ═══════════════════════════════════════════════
         KPI INDICATORS
         ═══════════════════════════════════════════════ --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">

        <x-filament::section>
            <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                Надходження
            </div>
            <div class="mt-1 text-3xl font-bold text-success-600 dark:text-success-400">
                {{ number_format($kpi['income'], 0, '.', ' ') }} ₴
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                Витрати
            </div>
            <div class="mt-1 text-3xl font-bold text-danger-600 dark:text-danger-400">
                {{ number_format($kpi['expenses'], 0, '.', ' ') }} ₴
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                Результат
            </div>
            <div class="mt-1 text-3xl font-bold
                {{ $kpi['result'] >= 0 ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}">
                {{ $kpi['result'] >= 0 ? '+' : '' }}{{ number_format($kpi['result'], 0, '.', ' ') }} ₴
            </div>
        </x-filament::section>

    </div>

    {{-- ═══════════════════════════════════════════════
         INCOME TABLE
         ═══════════════════════════════════════════════ --}}
    <x-filament::section heading="Надходження">

        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-200 dark:border-gray-700 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    <th class="pb-2 pr-6">Джерело</th>
                    <th class="pb-2 pr-6 text-right">Готівка</th>
                    <th class="pb-2 pr-6 text-right">
                        Безготівка
                        <span class="ml-1 rounded px-1 text-[10px] font-semibold normal-case tracking-normal bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300">CRM</span>
                    </th>
                    <th class="pb-2 pr-6 text-right">
                        Безготівка
                        <span class="ml-1 rounded px-1 text-[10px] font-semibold normal-case tracking-normal bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300">ПБ</span>
                    </th>
                    <th class="pb-2 text-right">Різниця</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                <tr>
                    <td class="py-2 pr-6 font-medium">Клієнти</td>
                    <td class="py-2 pr-6 text-right tabular-nums">
                        {{ number_format($income['crm_cash'], 0, '.', ' ') }} ₴
                    </td>
                    <td class="py-2 pr-6 text-right tabular-nums text-indigo-700 dark:text-indigo-400">
                        {{ number_format($income['crm_cashless'], 0, '.', ' ') }} ₴
                    </td>
                    <td class="py-2 pr-6 text-right tabular-nums">
                        @if ($income['pb_error'])
                            <span class="text-warning-500">помилка API</span>
                        @else
                            <span class="text-amber-700 dark:text-amber-400">
                                {{ number_format($income['pb_credit'], 0, '.', ' ') }} ₴
                            </span>
                        @endif
                    </td>
                    <td class="py-2 text-right tabular-nums">
                        @if (!$income['pb_error'])
                            @php $diff = $income['pb_diff']; @endphp
                            <span class="{{ abs($diff) < 0.01 ? 'text-success-600' : 'text-warning-600' }}">
                                {{ $diff > 0 ? '+' : '' }}{{ number_format($diff, 0, '.', ' ') }} ₴
                            </span>
                        @endif
                    </td>
                </tr>
            </tbody>
        </table>

        {{-- Per-account PrivatBank breakdown --}}
        @if (!$income['pb_error'] && count($income['pb_accounts']) > 0)
            <div class="mt-4 border-t border-gray-100 pt-4 dark:border-gray-800">
                <p class="mb-2 text-xs font-medium uppercase tracking-wide text-gray-400">
                    <span class="rounded px-1 bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300">ПБ</span>
                    Рахунки ПриватБанк
                </p>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($income['pb_accounts'] as $acc)
                        <div class="rounded-lg bg-amber-50 px-3 py-2 dark:bg-amber-900/10">
                            <div class="text-xs font-medium text-amber-800 dark:text-amber-300">{{ $acc['name'] }}</div>
                            <div class="mt-0.5 flex justify-between gap-4 text-sm">
                                <span class="text-success-600">↑ {{ number_format($acc['credit'], 0, '.', ' ') }} ₴</span>
                                <span class="text-danger-600">↓ {{ number_format($acc['debit'], 0, '.', ' ') }} ₴</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

    </x-filament::section>

    {{-- ═══════════════════════════════════════════════
         EXPENSES — summary table (all groups in one place)
         Group names are anchor-links → scroll to detail below.
         ═══════════════════════════════════════════════ --}}
    <x-filament::section heading="Витрати">

        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-200 dark:border-gray-700 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    <th class="pb-2 pr-6">Категорія</th>
                    <th class="pb-2 pr-6 text-right w-36">Готівка</th>
                    <th class="pb-2 pr-6 text-right w-48">
                        Безготівка
                        <span class="ml-1 rounded px-1 text-[10px] font-semibold normal-case tracking-normal bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300">CRM</span>
                        <span class="ml-0.5 rounded px-1 text-[10px] font-semibold normal-case tracking-normal bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300">ПБ</span>
                    </th>
                    <th class="pb-2 text-right w-36">
                        Всього
                        <span class="ml-1 rounded px-1 text-[10px] font-semibold normal-case tracking-normal bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300">CRM</span>
                    </th>
                </tr>
            </thead>
            <tbody>
                @foreach ($expenses as $groupKey => $group)
                    @php
                        $hasRows = count($group['rows'] ?? []) > 0;
                        // Group gets a clickable anchor if it has CRM rows OR PrivatBank cross-check data.
                        $hasPbData = ($groupKey === 'marketing' && isset($group['pb_google']))
                                  || ($groupKey === 'tax'       && isset($group['pb_tax_total']));
                        // Standard expense categories (expenses table) are always clickable so the
                        // user can see the (possibly empty) detail section and add new entries.
                        $isStandardExpense = !in_array($groupKey, ['production', 'uncategorized']);
                        $isClickable = $hasRows || $hasPbData || $isStandardExpense;
                    @endphp

                    {{-- Group row --}}
                    <tr class="border-t border-gray-100 dark:border-gray-800">
                        <td class="py-2 pr-6 font-semibold text-gray-800 dark:text-gray-200">
                            @if ($isClickable)
                                <a href="#detail-{{ $groupKey }}"
                                   class="inline-flex items-center gap-1 text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300">
                                    {{ $group['label'] }}
                                    <svg class="w-3 h-3 opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </a>
                            @else
                                <span class="{{ $group['total'] > 0 ? '' : 'text-gray-400' }}">{{ $group['label'] }}</span>
                            @endif
                        </td>
                        <td class="py-2 pr-6 text-right tabular-nums {{ $group['cash'] > 0 ? 'text-gray-700 dark:text-gray-300' : 'text-gray-300 dark:text-gray-600' }}">
                            {{ number_format($group['cash'], 0, '.', ' ') }} ₴
                        </td>

                        {{-- Безготівка: CRM value + PrivatBank value stacked where applicable --}}
                        @php
                            $pbCashless = null;
                            $pbHasError = false;
                            if ($groupKey === 'marketing' && isset($group['pb_google'])) {
                                if ($group['pb_error'] ?? false) { $pbHasError = true; }
                                else { $pbCashless = $group['pb_google']; }
                            } elseif ($groupKey === 'tax' && isset($group['pb_tax_total'])) {
                                if ($group['pb_tax_error'] ?? false) { $pbHasError = true; }
                                else { $pbCashless = $group['pb_tax_total']; }
                            }
                        @endphp
                        <td class="py-2 pr-6 text-right">
                            @if ($pbCashless !== null || $pbHasError)
                                <div class="flex flex-col items-end gap-0.5">
                                    {{-- CRM value --}}
                                    <div class="flex items-center gap-1.5">
                                        <span class="rounded px-1 text-[10px] font-semibold bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300">CRM</span>
                                        <span class="tabular-nums {{ $group['cashless'] > 0 ? 'text-indigo-700 dark:text-indigo-400' : 'text-gray-400' }}">
                                            {{ number_format($group['cashless'], 0, '.', ' ') }} ₴
                                        </span>
                                    </div>
                                    {{-- PrivatBank value --}}
                                    @if ($pbHasError)
                                        <div class="flex items-center gap-1.5">
                                            <span class="rounded px-1 text-[10px] font-semibold bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300">ПБ</span>
                                            <span class="text-[11px] text-warning-500">помилка API</span>
                                        </div>
                                    @else
                                        <div class="flex items-center gap-1.5">
                                            <span class="rounded px-1 text-[10px] font-semibold bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300">ПБ</span>
                                            <span class="tabular-nums text-amber-700 dark:text-amber-400">
                                                {{ number_format($pbCashless, 0, '.', ' ') }} ₴
                                            </span>
                                        </div>
                                        {{-- Difference (CRM is truth; PB is corrective) --}}
                                        @php $pbDiff = $pbCashless - $group['cashless']; @endphp
                                        @if (abs($pbDiff) > 0.5)
                                            <span class="text-[11px] tabular-nums {{ $pbDiff > 0 ? 'text-warning-600' : 'text-success-600' }}">
                                                {{ $pbDiff > 0 ? '+' : '' }}{{ number_format($pbDiff, 0, '.', ' ') }} ₴
                                            </span>
                                        @endif
                                    @endif
                                </div>
                            @else
                                <span class="tabular-nums {{ $group['cashless'] > 0 ? 'text-indigo-700 dark:text-indigo-400' : 'text-gray-300 dark:text-gray-600' }}">
                                    {{ number_format($group['cashless'], 0, '.', ' ') }} ₴
                                </span>
                            @endif
                        </td>

                        <td class="py-2 text-right font-semibold tabular-nums {{ $group['total'] > 0 ? 'text-gray-900 dark:text-white' : 'text-gray-300 dark:text-gray-600' }}">
                            {{ number_format($group['total'], 0, '.', ' ') }} ₴
                        </td>
                    </tr>

                    {{-- Sub-items (indented, smaller) --}}
                    @foreach ($group['items'] as $item)
                        <tr class="text-gray-500 dark:text-gray-400">
                            <td class="py-0.5 pl-5 pr-6 text-xs">
                                <span class="text-gray-400 mr-1">└</span>{{ $item['label'] }}
                            </td>
                            <td class="py-0.5 pr-6 text-right tabular-nums text-xs">
                                @if ($item['cash'] > 0) {{ number_format($item['cash'], 0, '.', ' ') }} ₴ @endif
                            </td>
                            <td class="py-0.5 pr-6 text-right tabular-nums text-xs text-indigo-600 dark:text-indigo-400">
                                @if ($item['cashless'] > 0) {{ number_format($item['cashless'], 0, '.', ' ') }} ₴ @endif
                            </td>
                            <td class="py-0.5 text-right tabular-nums text-xs">
                                {{ number_format($item['total'], 0, '.', ' ') }} ₴
                            </td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
            <tfoot>
                <tr class="border-t-2 border-gray-300 dark:border-gray-600">
                    <td class="pt-3 pr-6 font-bold text-gray-900 dark:text-white">Загальні витрати</td>
                    <td class="pt-3 pr-6 text-right font-semibold tabular-nums text-gray-700 dark:text-gray-300">
                        {{ number_format(collect($expenses)->sum('cash'), 0, '.', ' ') }} ₴
                    </td>
                    <td class="pt-3 pr-6 text-right font-semibold tabular-nums text-gray-700 dark:text-gray-300">
                        {{ number_format(collect($expenses)->sum('cashless'), 0, '.', ' ') }} ₴
                    </td>
                    <td class="pt-3 text-right text-2xl font-bold tabular-nums text-danger-600 dark:text-danger-400">
                        {{ number_format(collect($expenses)->sum('total'), 0, '.', ' ') }} ₴
                    </td>
                </tr>
            </tfoot>
        </table>

    </x-filament::section>

    {{-- ═══════════════════════════════════════════════
         EXPENSE DETAIL SECTIONS (one per group, below)
         Anchor targets for the summary table links above.
         scroll-mt-20 compensates for the fixed Filament top bar.
         ═══════════════════════════════════════════════ --}}
    @foreach ($expenses as $groupKey => $group)
        @php
            $hasRows   = count($group['rows'] ?? []) > 0;
            // Render this detail section if there are CRM rows OR there is PrivatBank
            // cross-check data for this group — whichever comes first.
            $hasPbBlock = ($groupKey === 'marketing' && isset($group['pb_google']))
                       || ($groupKey === 'tax'       && isset($group['pb_tax_total']));
            // Standard expense categories always get a visible section — even empty —
            // so the user can see the table structure and navigate to add new entries.
            $isStandardExpense = !in_array($groupKey, ['production', 'uncategorized']);
        @endphp
        @if ($hasRows || $hasPbBlock || $isStandardExpense)
            <div id="detail-{{ $groupKey }}" style="scroll-margin-top: 80px">
                <x-filament::section :heading="$group['label']">

                    {{-- ── ПриватБанк: Google Ads (marketing only) ── --}}
                    @if ($groupKey === 'marketing' && isset($group['pb_google']))
                        <div class="mb-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm dark:border-amber-800 dark:bg-amber-900/20">
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-400">
                                ПриватБанк — Google Ads
                            </p>
                            @if ($group['pb_error'] ?? false)
                                <span class="text-warning-600">Помилка API — не вдалось завантажити дані</span>
                            @else
                                @php $googleDiff = $group['pb_google_diff'] ?? 0; @endphp
                                <div class="flex flex-wrap gap-6 text-gray-700 dark:text-gray-200">
                                    <span>
                                        CRM:
                                        <strong class="tabular-nums">
                                            {{ number_format(collect($group['items'])->firstWhere('label', 'Google')['total'] ?? 0, 0, '.', ' ') }} ₴
                                        </strong>
                                    </span>
                                    <span>
                                        ПриватБанк:
                                        <strong class="tabular-nums">{{ number_format($group['pb_google'], 0, '.', ' ') }} ₴</strong>
                                    </span>
                                    <span class="{{ abs($googleDiff) < 0.01 ? 'text-success-600' : 'text-warning-600' }}">
                                        Різниця: {{ $googleDiff > 0 ? '+' : '' }}{{ number_format($googleDiff, 0, '.', ' ') }} ₴
                                    </span>
                                </div>
                                <p class="mt-1 text-xs text-gray-400">Фільтр: дебетові транзакції зі словом "Google" в призначенні</p>
                            @endif
                        </div>
                    @endif

                    {{-- ── ПриватБанк: Оплата податків ФОП (tax only) ── --}}
                    @if ($groupKey === 'tax' && isset($group['pb_tax_total']))
                        <div class="mb-5 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm dark:border-blue-800 dark:bg-blue-900/20">
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-blue-700 dark:text-blue-400">
                                ПриватБанк — Оплата податків ФОП
                            </p>
                            @if ($group['pb_tax_error'] ?? false)
                                <span class="text-warning-600">Помилка API — не вдалось завантажити дані</span>
                            @elseif ($group['pb_tax_total'] <= 0)
                                <span class="text-gray-500 dark:text-gray-400">За цей період оплат по податках не виявлено</span>
                                <p class="mt-1 text-xs text-gray-400">Шукаємо: "єдиний податок", "єсв", "пдфо" в призначенні; або "казначейство", "дпс" в контрагенті</p>
                            @else
                                <div class="mb-3 flex flex-wrap items-baseline gap-4 text-gray-700 dark:text-gray-200">
                                    <span>Разом по всіх рахунках:
                                        <strong class="tabular-nums text-blue-700 dark:text-blue-300">
                                            {{ number_format($group['pb_tax_total'], 0, '.', ' ') }} ₴
                                        </strong>
                                    </span>
                                </div>
                                @foreach ($group['pb_tax_accounts'] as $acc)
                                    <div class="mt-3">
                                        <div class="mb-1 flex items-center gap-3">
                                            <span class="text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">
                                                {{ $acc['name'] }}
                                            </span>
                                            <span class="tabular-nums font-semibold text-blue-600 dark:text-blue-400">
                                                {{ number_format($acc['total'], 0, '.', ' ') }} ₴
                                            </span>
                                        </div>
                                        <table class="w-full text-xs">
                                            <thead>
                                                <tr class="border-b border-blue-100 text-left text-gray-400 dark:border-blue-900">
                                                    <th class="pb-1 pr-4 w-24 font-normal">Дата</th>
                                                    <th class="pb-1 pr-4 font-normal">Контрагент / Призначення</th>
                                                    <th class="pb-1 text-right w-28 font-normal">Сума</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-blue-50 dark:divide-blue-900/30">
                                                @foreach ($acc['transactions'] as $tx)
                                                    <tr class="text-gray-600 dark:text-gray-400">
                                                        <td class="py-1 pr-4 text-gray-400">{{ $tx['date'] ?? '—' }}</td>
                                                        <td class="py-1 pr-4">
                                                            @if (!empty($tx['counterparty']))
                                                                <span class="font-medium text-gray-700 dark:text-gray-300">
                                                                    {{ $tx['counterparty'] }}
                                                                </span>
                                                            @endif
                                                            @if (!empty($tx['osnd']))
                                                                <span class="block text-gray-400" title="{{ $tx['osnd'] }}">
                                                                    {{ Str::limit($tx['osnd'], 120) }}
                                                                </span>
                                                            @endif
                                                        </td>
                                                        <td class="py-1 text-right tabular-nums font-medium">
                                                            {{ number_format($tx['amount'], 0, '.', ' ') }} ₴
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endforeach
                                <p class="mt-2 text-xs text-gray-400">Фільтр: "єдиний податок", "єсв", "пдфо" в призначенні; або "казначейство", "дпс", "державна податкова" в контрагенті</p>
                            @endif
                        </div>
                    @endif

                    {{-- ── CRM-дані: таблиця платежів ── --}}
                    @if ($hasRows)
                        @if ($hasPbBlock)
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">Дані CRM</p>
                        @endif
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 dark:border-gray-700 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    <th class="pb-2 pr-4 w-28">Дата</th>
                                    <th class="pb-2 pr-4">Контрагент / Опис</th>
                                    <th class="pb-2 pr-4 text-right w-32">Готівка</th>
                                    <th class="pb-2 pr-4 text-right w-32">Безготівка</th>
                                    <th class="pb-2 text-right w-32">Сума</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50 dark:divide-gray-800/50">
                                @foreach ($group['rows'] as $row)
                                    <tr class="text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800/30">
                                        <td class="py-1.5 pr-4 whitespace-nowrap text-gray-400 text-xs">
                                            {{ $row['date'] ? \Carbon\Carbon::parse($row['date'])->format('d.m.Y') : '—' }}
                                        </td>
                                        <td class="py-1.5 pr-4">
                                            @if (!empty($row['label']) && $row['label'] !== '—')
                                                <span class="font-medium text-gray-700 dark:text-gray-300">{{ $row['label'] }}</span>
                                            @endif
                                            @if (!empty($row['type']) && $row['type'] !== $row['label'])
                                                <span class="ml-1 text-xs text-gray-400">({{ $row['type'] }})</span>
                                            @endif
                                            @if (!empty($row['comment']))
                                                <span class="block text-xs text-gray-400" title="{{ $row['comment'] }}">
                                                    {{ Str::limit($row['comment'], 80) }}
                                                </span>
                                            @endif
                                        </td>
                                        <td class="py-1.5 pr-4 text-right tabular-nums">
                                            @if ($row['cash'] > 0) {{ number_format($row['cash'], 0, '.', ' ') }} ₴ @endif
                                        </td>
                                        <td class="py-1.5 pr-4 text-right tabular-nums">
                                            @if ($row['cashless'] > 0) {{ number_format($row['cashless'], 0, '.', ' ') }} ₴ @endif
                                        </td>
                                        <td class="py-1.5 text-right font-medium tabular-nums">
                                            {{ number_format($row['total'], 0, '.', ' ') }} ₴
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="border-t border-gray-200 dark:border-gray-700 font-semibold text-gray-700 dark:text-gray-300">
                                    <td class="pt-2 pr-4 text-xs text-gray-400" colspan="2">
                                        Разом: {{ count($group['rows']) }} {{ count($group['rows']) === 1 ? 'платіж' : (count($group['rows']) < 5 ? 'платежі' : 'платежів') }}
                                    </td>
                                    <td class="pt-2 pr-4 text-right tabular-nums">
                                        @php $rc = collect($group['rows'])->sum('cash'); @endphp
                                        @if ($rc > 0) {{ number_format($rc, 0, '.', ' ') }} ₴ @endif
                                    </td>
                                    <td class="pt-2 pr-4 text-right tabular-nums">
                                        @php $rl = collect($group['rows'])->sum('cashless'); @endphp
                                        @if ($rl > 0) {{ number_format($rl, 0, '.', ' ') }} ₴ @endif
                                    </td>
                                    <td class="pt-2 text-right tabular-nums text-gray-900 dark:text-white">
                                        {{ number_format($group['total'], 0, '.', ' ') }} ₴
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    @elseif (!$hasPbBlock)
                        <div class="py-6 text-center">
                            <p class="text-sm text-gray-400">Немає записів за цей період</p>
                            @if ($isStandardExpense)
                                {{-- ExpenseResource is gone — expenses are now created on the "Платежі" page --}}
                                <a href="{{ \App\Filament\Pages\Finance\Payments::getUrl() }}"
                                   class="mt-2 inline-flex items-center gap-1 text-xs text-primary-600 hover:text-primary-700 dark:text-primary-400">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                                    </svg>
                                    Додати витрату
                                </a>
                            @endif
                        </div>
                    @endif

                </x-filament::section>
            </div>
        @endif
    @endforeach

</x-filament-panels::page>
