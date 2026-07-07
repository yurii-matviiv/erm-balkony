<x-filament-panels::page>

    {{-- ═══════════════════════════════════════════════
         BLOCK 1 — legacy migration overview (read-only)
         ═══════════════════════════════════════════════ --}}
    <x-filament::section
        heading="Міграція зі старої CRM"
        description="Як старі платежі розкладаються в нову структуру. Правила виконуються при синхронізації; ручні вердикти (розібрано/анульовано) авто-синк не перезаписує."
    >
        {{-- Classification totals --}}
        @php($totals = $this->getClassificationTotals())
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-lg bg-success-50 p-4 dark:bg-success-500/10">
                <div class="text-sm text-gray-500 dark:text-gray-400">Розібрані</div>
                <div class="text-2xl font-bold text-success-600 dark:text-success-400">{{ number_format($totals['classified'], 0, '.', ' ') }}</div>
            </div>
            <div class="rounded-lg bg-warning-50 p-4 dark:bg-warning-500/10">
                <div class="text-sm text-gray-500 dark:text-gray-400">Не розібрані (ручна черга)</div>
                <div class="text-2xl font-bold text-warning-600 dark:text-warning-400">{{ number_format($totals['unsorted'], 0, '.', ' ') }}</div>
            </div>
            <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-500/10">
                <div class="text-sm text-gray-500 dark:text-gray-400">Анульовані (дублі/застарілі)</div>
                <div class="text-2xl font-bold text-gray-600 dark:text-gray-300">{{ number_format($totals['annulled'], 0, '.', ' ') }}</div>
            </div>
        </div>

        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
            Групи доступні фільтром "Розбір" на сторінці
            <a href="{{ \App\Filament\Pages\Finance\Payments::getUrl() }}" class="font-medium text-primary-600 hover:underline dark:text-primary-400">Платежі</a>.
        </p>

        {{-- Per-mapper field maps --}}
        <div class="mt-6 space-y-6">
            @foreach ($this->getPaymentMappers() as $mapper)
                <div class="rounded-lg border border-gray-200 dark:border-gray-700">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                        <div class="font-medium">{{ $mapper['label'] }}</div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">
                            <span class="font-mono">{{ $mapper['old'] }}</span>
                            →
                            <span class="font-mono">{{ $mapper['new'] }}</span>
                            &nbsp;·&nbsp; у старій БД: {{ number_format($mapper['oldCount'], 0, '.', ' ') }}
                            &nbsp;·&nbsp; синхронізовано: {{ number_format($mapper['syncedCount'], 0, '.', ' ') }}
                        </div>
                    </div>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs uppercase text-gray-400">
                                <th class="px-4 py-2">Старе поле</th>
                                <th class="px-4 py-2">Нове поле</th>
                                <th class="px-4 py-2">Логіка</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($mapper['fieldMap'] as $row)
                                <tr class="border-t border-gray-100 dark:border-gray-800">
                                    <td class="px-4 py-2 font-mono text-xs">{{ $row['old'] }}</td>
                                    <td class="px-4 py-2 font-mono text-xs">{{ $row['new'] }}</td>
                                    <td class="px-4 py-2 text-gray-600 dark:text-gray-300">{{ $row['note'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach
        </div>
    </x-filament::section>

    {{-- ═══════════════════════════════════════════════
         BLOCK 2 — new-system distribution rules (editable)
         ═══════════════════════════════════════════════ --}}
    {{ $this->table }}

    {{-- Footer (PageDoc) renders automatically via getFooter() --}}

</x-filament-panels::page>
