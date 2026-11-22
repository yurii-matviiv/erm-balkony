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

    <div class="flex flex-wrap items-end gap-6">
        @include('filament.components.bar-select', ['label' => 'Знімок', 'model' => 'snapshot', 'options' => $this->snapshotOptions()])

        @if (in_array($this->activeTab, ['changes', 'new_page_query', 'top_query', 'prospects'], true))
            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200" style="height:2.5rem;">
                <x-filament::input.checkbox wire:model.live="hideExcluded" />
                <span>Ховати виключені слова ({{ $this->excludedCount() }})</span>
            </label>
        @endif
    </div>

    {{ $this->table }}
</x-filament-panels::page>
