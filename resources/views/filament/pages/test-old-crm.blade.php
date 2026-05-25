<x-filament-panels::page>

    <div class="space-y-6">

        <div class="bg-white rounded-xl shadow p-6">
            <h2 class="text-xl font-bold mb-4">Old CRM read-only test</h2>

            <div class="grid grid-cols-4 gap-4 mb-6">
                <div class="bg-gray-100 rounded p-4">
                    <div class="text-sm text-gray-500">Users</div>
                    <div class="text-2xl font-bold">{{ $data['users_count'] }}</div>
                </div>

                <div class="bg-gray-100 rounded p-4">
                    <div class="text-sm text-gray-500">Clients</div>
                    <div class="text-2xl font-bold">{{ $data['clients_count'] }}</div>
                </div>

                <div class="bg-gray-100 rounded p-4">
                    <div class="text-sm text-gray-500">Leads</div>
                    <div class="text-2xl font-bold">{{ $data['leads_count'] }}</div>
                </div>

                <div class="bg-gray-100 rounded p-4">
                    <div class="text-sm text-gray-500">Orders</div>
                    <div class="text-2xl font-bold">{{ $data['orders_count'] }}</div>
                </div>
            </div>

            <h3 class="font-bold mb-2">Latest leads</h3>

            <pre class="bg-gray-100 p-4 rounded overflow-auto text-xs">{{ json_encode($data['latest_leads'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        </div>

    </div>

</x-filament-panels::page>
