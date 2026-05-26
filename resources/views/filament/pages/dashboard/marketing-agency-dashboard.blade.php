<x-filament-panels::page>

    <div class="overflow-x-auto">

        <table class="w-full text-sm border-collapse">

            <thead>

                <tr class="border-b">

                    <th class="text-left p-2">ID</th>

                    <th class="text-left p-2">Date</th>

                    <th class="text-left p-2">Name</th>

                    <th class="text-left p-2">Phone</th>

                    <th class="text-left p-2">Email</th>

                    <th class="text-left p-2">Status</th>

                    <th class="text-left p-2">UTM Source</th>

                    <th class="text-left p-2">Campaign</th>

                    <th class="text-left p-2">Price</th>

                </tr>

            </thead>

            <tbody>

                @foreach($this->leads as $lead)

                    <tr class="border-b">

                        <td class="p-2">
                            {{ $lead->id ?? '' }}
                        </td>

                        <td class="p-2">
                            {{ $lead->created_at ?? '' }}
                        </td>

                        <td class="p-2">
                            {{ $lead->name ?? '' }}
                        </td>

                        <td class="p-2">
                            {{ $lead->phone ?? '' }}
                        </td>

                        <td class="p-2">
                            {{ $lead->email ?? '' }}
                        </td>

                        <td class="p-2">
                            {{ $lead->status ?? '' }}
                        </td>

                        <td class="p-2">
                            {{ $lead->utm_source ?? '' }}
                        </td>

                        <td class="p-2">
                            {{ $lead->utm_campaign ?? '' }}
                        </td>

                        <td class="p-2">
                            {{ $lead->total_price ?? '' }}
                        </td>

                    </tr>

                @endforeach

            </tbody>

        </table>

    </div>

</x-filament-panels::page>
