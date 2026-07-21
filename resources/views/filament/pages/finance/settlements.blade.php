<x-filament-panels::page>

    {{-- ═══════════════════════════════════════════════
         DATE BAR — same pattern as "Платежі"/invoice-analytics: preset
         fills the two date inputs; editing a date flips the preset to
         "Довільний період".
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

    @php($indicators = $this->getIndicators())

    {{-- ═══════════════════════════════════════════════
         INDICATORS — exactly three (per ТЗ): баланс (весь час, навмисно
         не залежить від фільтра дат) + переказано кожному учаснику за
         обраний період.
         ═══════════════════════════════════════════════ --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">

        <x-filament::section>
            <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                Баланс (весь час)
            </div>
            <div class="mt-1 text-3xl font-bold
                {{ $indicators['balance'] >= 0 ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}">
                {{ number_format($indicators['balance'], 0, '.', ' ') }} ₴
            </div>
        </x-filament::section>

        @foreach ($indicators['participants'] as $row)
            <x-filament::section>
                <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Переказано — {{ $row['user']->name }} (період)
                </div>
                <div class="mt-1 text-3xl font-bold text-info-600 dark:text-info-400">
                    {{ number_format($row['transferred'], 0, '.', ' ') }} ₴
                </div>
            </x-filament::section>
        @endforeach

    </div>

    {{-- Hint until participants are configured: transfer buttons and
         per-user indicators only appear after the super_admin picks the
         participants via the "Учасники" header action. --}}
    @if (empty($indicators['participants']))
        <x-filament::section>
            <div class="text-sm text-gray-500 dark:text-gray-400">
                Учасників ще не налаштовано — кнопки переказів з'являться після того,
                як адміністратор обере користувачів через дію «Учасники» вгорі сторінки.
            </div>
        </x-filament::section>
    @endif

    {{ $this->table }}

</x-filament-panels::page>
