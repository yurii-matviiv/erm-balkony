<x-filament-panels::page>

    <x-filament::section>

        <div class="overflow-x-auto">

            <table class="fi-ta-table w-full table-auto divide-y divide-gray-200 text-sm">

                <thead class="bg-gray-50 dark:bg-white/5">

                <tr>

                    <th class="px-4 py-3 text-left font-semibold whitespace-nowrap">
                        Title
                    </th>

                    <th class="px-4 py-3 text-left font-semibold whitespace-nowrap">
                        Permission
                    </th>

                    @foreach($roles as $role)

                        <th class="px-4 py-3 text-center font-semibold whitespace-nowrap">
                            {{ $role }}
                        </th>

                    @endforeach

                </tr>

                </thead>

                <tbody class="divide-y divide-gray-200 dark:divide-white/10">

                @foreach($permissions as $permission)

                    <tr class="hover:bg-gray-50 dark:hover:bg-white/5">

                        <td class="px-4 py-3 font-medium whitespace-nowrap">
                            {{ $permissionLabels[$permission] ?? '—' }}
                        </td>

                        <td class="px-4 py-3 text-gray-500 whitespace-nowrap">
                            {{ $permission }}
                        </td>

                        @foreach($roles as $role)

                            <td class="px-4 py-3 text-center">

                                <input
                                    type="checkbox"
                                    wire:change="togglePermission('{{ $role }}', '{{ $permission }}')"
                                    @checked($matrix[$permission][$role] ?? false)
                                    class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500"
                                >

                            </td>

                        @endforeach

                    </tr>

                @endforeach

                </tbody>

            </table>

        </div>

    </x-filament::section>

</x-filament-panels::page>