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

    {{-- Вибір знімка: один рядок, завжди на виду (стандартного «завжди видимого» фільтра з одним полем у Filament немає). --}}
    <div style="display:flex;align-items:center;gap:.75rem;">
        <span class="fi-fo-field-label-content" style="white-space:nowrap;">Знімок:</span>
        <div style="width:12rem;">
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="snapshot">
                    @foreach ($this->snapshotOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
