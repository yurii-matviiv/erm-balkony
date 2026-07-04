<x-filament-panels::page>

    {{-- If we arrived here via the "Додати заявку" sidebar shortcut
         (?create=1), open the create modal as soon as Livewire/Alpine has
         finished hydrating. $wire is an Alpine magic that resolves to the
         nearest Livewire component — works here because this div sits
         inside this page's Livewire-rendered DOM. --}}
    @if (request()->boolean('create'))
        <div x-data x-init="$wire.mountAction('create')"></div>
    @endif

    {{-- Документація сторінки тепер живе на сторінці конкретного ліда
         (EditLead), не тут — список має лишатись просто таблицею. --}}

    {{ $this->table }}

</x-filament-panels::page>
