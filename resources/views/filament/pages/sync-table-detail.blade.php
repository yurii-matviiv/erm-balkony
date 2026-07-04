<x-filament-panels::page>

    {{-- Field mapping legend: shows which old column becomes which new
         column, and any note about how the value is transformed (or not
         transformed at all, e.g. password/role). --}}
    <x-filament::section heading="Як мапляться поля" collapsible>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 dark:text-gray-400">
                        <th class="py-1 pr-4">Стара БД</th>
                        <th class="py-1 pr-4"></th>
                        <th class="py-1 pr-4">Нова БД</th>
                        <th class="py-1">Примітка</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->getFieldMap() as $field)
                        <tr class="border-t border-gray-100 dark:border-gray-700">
                            <td class="py-1.5 pr-4 font-mono">{{ $field['old'] }}</td>
                            <td class="py-1.5 pr-4 text-gray-400">→</td>
                            <td class="py-1.5 pr-4 font-mono">{{ $field['new'] }}</td>
                            <td class="py-1.5 text-gray-500 dark:text-gray-400">{{ $field['note'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>

    {{-- Side-by-side comparison: raw data from the old table (left) vs.
         the matching, already-synced rows in the new table (right). --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

        <x-filament::section heading="Стара база даних">
            @php($oldRecords = $this->getOldRecords())

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 dark:text-gray-400">
                            @foreach (array_keys((array) ($oldRecords->first() ?? [])) as $column)
                                <th class="py-1 pr-4 whitespace-nowrap">{{ $column }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($oldRecords as $row)
                            <tr class="border-t border-gray-100 dark:border-gray-700">
                                @foreach ((array) $row as $value)
                                    <td class="py-1.5 pr-4 whitespace-nowrap">{{ $value }}</td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td class="py-4 text-gray-400">Немає даних.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                @include('filament.components.simple-pager', ['paginator' => $oldRecords])
            </div>
        </x-filament::section>

        <x-filament::section heading="Нова база даних (вже синхронізовано)">
            @php($newRecords = $this->getNewRecords())

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 dark:text-gray-400">
                            @foreach (array_keys((array) ($newRecords->first() ?? [])) as $column)
                                <th class="py-1 pr-4 whitespace-nowrap">{{ $column }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($newRecords as $row)
                            <tr class="border-t border-gray-100 dark:border-gray-700">
                                @foreach ((array) $row as $value)
                                    <td class="py-1.5 pr-4 whitespace-nowrap">{{ $value }}</td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td class="py-4 text-gray-400">Ще немає синхронізованих записів — натисни «Синхронізувати» вгорі.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                @include('filament.components.simple-pager', ['paginator' => $newRecords])
            </div>
        </x-filament::section>

    </div>

</x-filament-panels::page>
