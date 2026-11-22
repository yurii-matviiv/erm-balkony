<x-filament-panels::page>
    {{-- Стандартні вкладки Filament (як на сторінці «Нагадування») + стандартна таблиця. --}}
    <x-filament::tabs>
        @foreach ($this->getTabs() as $key => $label)
            <x-filament::tabs.item
                :active="$this->activeTab === $key"
                wire:click="setTab('{{ $key }}')">
                {{ $label }}
            </x-filament::tabs.item>
        @endforeach
    </x-filament::tabs>

    {{ $this->table }}
</x-filament-panels::page>
