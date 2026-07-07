<x-filament-panels::page>

    {{-- ═══════════════════════════════════════════════
         DATE BAR — always visible, full width, page-level.
         Same pattern as invoice-analytics: preset fills the two date
         inputs; editing a date flips the preset to "Довільний період".
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
                    @foreach ($this->getPresetOptions() as $value => $label)
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
                    {{ $this->getPresetOptions()[$preset] ?? $preset }}:
                    <span class="font-medium text-gray-700 dark:text-gray-300">
                        {{ $dateFrom ? \Carbon\Carbon::parse($dateFrom)->format('d.m.Y') : '…' }}
                        —
                        {{ $dateTo ? \Carbon\Carbon::parse($dateTo)->format('d.m.Y') : '…' }}
                    </span>
                </span>
            </div>

        </div>
    </x-filament::section>

    {{-- ═══════════════════════════════════════════════
         LOADING FEEDBACK — explicit user request: with large periods it
         was impossible to tell whether filtering is running or the page
         hung. `wire:loading` (no target) reacts to EVERY Livewire
         round-trip on this page: filters, dates, search, sort,
         pagination. While loading — spinner + message and the table dims;
         when idle — a green "done" line with the render time.
         ═══════════════════════════════════════════════ --}}
    <div class="flex items-center gap-2 text-sm" aria-live="polite">
        <div wire:loading.flex class="items-center gap-2 font-medium text-primary-600 dark:text-primary-400">
            <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
            </svg>
            Фільтрування даних…
        </div>
        <div wire:loading.remove class="flex items-center gap-1.5 text-success-600 dark:text-success-400">
            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
            </svg>
            Вибірку завершено о {{ now()->format('H:i:s') }}
        </div>
    </div>

    <div wire:loading.class="pointer-events-none opacity-50" class="transition-opacity">
        {{ $this->table }}
    </div>

</x-filament-panels::page>
