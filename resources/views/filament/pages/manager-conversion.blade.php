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

    @if (count($managers) === 0)
        <x-filament::section>
            <div class="py-6 text-center text-sm text-gray-400">За цей період немає даних по менеджерах.</div>
        </x-filament::section>
    @else
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($managers as $m)
                @php
                    $rateColor = $m['rate'] >= 30 ? 'text-success-600 dark:text-success-400'
                        : ($m['rate'] >= 15 ? 'text-warning-600 dark:text-warning-400' : 'text-danger-600 dark:text-danger-400');
                @endphp
                <x-filament::section>
                    <div class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ $m['name'] }}</div>

                    <div class="mt-4 grid grid-cols-2 gap-4">
                        <div>
                            <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Заявок прийнято</div>
                            <div class="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($m['leads'], 0, '.', ' ') }}</div>
                        </div>
                        <div>
                            <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">В замовлення</div>
                            <div class="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($m['converted'], 0, '.', ' ') }}</div>
                        </div>
                        <div>
                            <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Конверсія</div>
                            <div class="mt-1 text-2xl font-bold {{ $rateColor }}">{{ number_format($m['rate'], 1, '.', ' ') }}%</div>
                        </div>
                        <div>
                            <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Оборот</div>
                            <div class="mt-1 text-2xl font-bold text-success-600 dark:text-success-400">{{ number_format($m['turnover'], 0, '.', ' ') }} ₴</div>
                        </div>
                    </div>

                    <div class="mt-4 h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                        <div class="h-2 rounded-full bg-primary-500" style="width: {{ min(100, $m['rate']) }}%"></div>
                    </div>
                    <div class="mt-2 text-xs text-gray-400 dark:text-gray-500">
                        Замовлень створено за період: {{ number_format($m['orders'], 0, '.', ' ') }}
                    </div>
                </x-filament::section>
            @endforeach
        </div>
    @endif

</x-filament-panels::page>
