<x-filament-panels::page>

    {{-- Top-level summary: total applications, how many were "цільові",
         and a quick breakdown by vacancy / channel. Intentionally simple
         numbers/lists rather than charts — can be upgraded later once
         there's enough real data to make a chart meaningful. --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-4">

        <x-filament::section>
            <div class="text-sm text-gray-500 dark:text-gray-400">Всього заявок</div>
            <div class="text-3xl font-bold">{{ $total }}</div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-sm text-gray-500 dark:text-gray-400">Цільових заявок</div>
            <div class="text-3xl font-bold">
                {{ $targeted }}
                @if ($total > 0)
                    <span class="text-base font-normal text-gray-400">({{ round($targeted / $total * 100) }}%)</span>
                @endif
            </div>
        </x-filament::section>

        <x-filament::section heading="За вакансіями" class="lg:col-span-1">
            <ul class="space-y-1 text-sm">
                @forelse ($byVacancy as $vacancy)
                    <li class="flex justify-between gap-2">
                        <span>{{ $vacancy->name }}</span>
                        <span class="font-semibold">{{ $vacancy->applications_count }}</span>
                    </li>
                @empty
                    <li class="text-gray-400">Немає вакансій.</li>
                @endforelse
            </ul>
        </x-filament::section>

        <x-filament::section heading="За каналами" class="lg:col-span-1">
            <ul class="space-y-1 text-sm">
                @forelse ($byChannel as $channel => $count)
                    <li class="flex justify-between gap-2">
                        <span>{{ $channelLabels[$channel] ?? ($channel ?: 'Не вказано') }}</span>
                        <span class="font-semibold">{{ $count }}</span>
                    </li>
                @empty
                    <li class="text-gray-400">Немає заявок.</li>
                @endforelse
            </ul>
        </x-filament::section>

    </div>

    {{-- Filterable raw list underneath, for drilling into any of the
         numbers above. --}}
    {{ $this->table }}

</x-filament-panels::page>
