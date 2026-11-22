<x-filament-panels::page>
    {{-- Стандартні вкладки Filament (вигляд — глобальний tabs-styles), далі стандартний бар вибору, далі стандартна таблиця. --}}
    <x-filament::tabs>
        @foreach ($this->getTabs() as $key => $label)
            <x-filament::tabs.item
                :active="$this->activeTab === $key"
                wire:click="setTab('{{ $key }}')">
                {{ $label }}
            </x-filament::tabs.item>
        @endforeach
    </x-filament::tabs>

    @include('filament.components.bar-select', ['label' => 'Знімок', 'model' => 'snapshot', 'options' => $this->snapshotOptions()])

    {{ $this->table }}
</x-filament-panels::page>
