<x-filament-panels::page>

    {{-- ═══════════════════════════════════════════════
         FILTER — date range (same pattern as "Аналітика рахунків") + вакансія
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

            {{-- Vacancy filter --}}
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-500 dark:text-gray-400">Вакансія</label>
                <select
                    wire:model.live="vacancyId"
                    class="block rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm
                           focus:border-primary-500 focus:ring-primary-500
                           dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                >
                    <option value="">Усі вакансії</option>
                    @foreach ($vacancyOptions as $id => $name)
                        <option value="{{ $id }}" @selected((string) $vacancyId === (string) $id)>{{ $name }}</option>
                    @endforeach
                </select>
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
         "СЬОГОДНІ" — завжди поточний день, незалежно від фільтру вище
         ═══════════════════════════════════════════════ --}}
    <x-filament::section heading="Сьогодні" icon="heroicon-o-bolt">
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Переглядів</div>
                <div class="text-2xl font-bold">{{ $today['views'] }}</div>
            </div>
            <div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Унікальних</div>
                <div class="text-2xl font-bold">{{ $today['uniqueVisitors'] }}</div>
            </div>
            <div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Заявок</div>
                <div class="text-2xl font-bold">{{ $today['applications'] }}</div>
            </div>
            <div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Конверсія</div>
                <div class="text-2xl font-bold">{{ $today['conversion'] !== null ? $today['conversion'].'%' : '—' }}</div>
            </div>
        </div>
    </x-filament::section>

    {{-- ═══════════════════════════════════════════════
         KPI — за обраний період
         ═══════════════════════════════════════════════ --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
        <x-filament::section>
            <div class="text-sm text-gray-500 dark:text-gray-400">Переглядів форми</div>
            <div class="text-3xl font-bold">{{ $summary['views'] }}</div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-sm text-gray-500 dark:text-gray-400">Унікальних відвідувачів</div>
            <div class="text-3xl font-bold">{{ $summary['uniqueVisitors'] }}</div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-sm text-gray-500 dark:text-gray-400">Заявок</div>
            <div class="text-3xl font-bold">{{ $summary['applications'] }}</div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-sm text-gray-500 dark:text-gray-400">Конверсія у заявку</div>
            <div class="text-3xl font-bold">{{ $summary['conversion'] !== null ? $summary['conversion'].'%' : '—' }}</div>
        </x-filament::section>
    </div>

    {{-- ═══════════════════════════════════════════════
         ДЖЕРЕЛО (UTM) — звідки приходить заявка
         ═══════════════════════════════════════════════ --}}
    <x-filament::section heading="Джерело переходу (UTM)">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-left text-xs text-gray-500 dark:border-gray-700 dark:text-gray-400">
                        <th class="py-2 pr-4">Джерело</th>
                        <th class="py-2 pr-4 text-right">Переглядів</th>
                        <th class="py-2 pr-4 text-right">Унікальних</th>
                        <th class="py-2 pr-4 text-right">Заявок</th>
                        <th class="py-2 text-right">Конверсія</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sources as $row)
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <td class="py-2 pr-4">{{ $row['source'] }}</td>
                            <td class="py-2 pr-4 text-right">{{ $row['views'] }}</td>
                            <td class="py-2 pr-4 text-right">{{ $row['uniqueVisitors'] }}</td>
                            <td class="py-2 pr-4 text-right">{{ $row['applications'] }}</td>
                            <td class="py-2 text-right">{{ $row['conversion'] !== null ? $row['conversion'].'%' : '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-4 text-center text-gray-400">Немає даних за обраний період.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    {{-- ═══════════════════════════════════════════════
         ПО ДНЯХ — тренд за обраний період
         ═══════════════════════════════════════════════ --}}
    <x-filament::section heading="По днях">
        @if ($dailyTruncated)
            <p class="mb-3 text-xs text-gray-400 dark:text-gray-500">
                Обраний період довший за 90 днів — таблиця нижче показує лише останні 90 днів. Картки вище й досі рахують за весь обраний період.
            </p>
        @endif

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-left text-xs text-gray-500 dark:border-gray-700 dark:text-gray-400">
                        <th class="py-2 pr-4">Дата</th>
                        <th class="py-2 pr-4 text-right">Переглядів</th>
                        <th class="py-2 pr-4 text-right">Унікальних</th>
                        <th class="py-2 text-right">Заявок</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($dailyRows as $row)
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <td class="py-2 pr-4">{{ \Carbon\Carbon::parse($row['date'])->format('d.m.Y') }}</td>
                            <td class="py-2 pr-4 text-right">{{ $row['views'] }}</td>
                            <td class="py-2 pr-4 text-right">{{ $row['uniqueVisitors'] }}</td>
                            <td class="py-2 text-right">{{ $row['applications'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-4 text-center text-gray-400">Немає даних за обраний період.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

</x-filament-panels::page>
