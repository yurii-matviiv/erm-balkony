<x-filament-panels::page>
    {{-- Стандартні вкладки Filament (вигляд — глобальний tabs-styles) + стандартна таблиця. Показники слова — header-віджет. --}}
    <x-filament::tabs>
        @foreach ($this->getTabs() as $key => $label)
            <x-filament::tabs.item
                :active="$this->activeTab === $key"
                wire:click="setTab('{{ $key }}')">
                {{ $label }}
            </x-filament::tabs.item>
        @endforeach
    </x-filament::tabs>

    @if ($this->activeTab === 'crm')
        {{-- Пояснення, чого ці цифри НЕ означають. --}}
        <p class="text-sm text-gray-500 dark:text-gray-400">Заявки й замовлення зі сторінок, на яких слово ранжується (за адресою сторінки в маркетингових даних заявки). Це не заявки саме по цьому слову: Google не передає слово в заявку, лише сторінку.</p>
    @endif

    {{ $this->table }}
</x-filament-panels::page>
