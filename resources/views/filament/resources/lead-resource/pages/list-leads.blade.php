<x-filament-panels::page>

    {{-- Компактна шапка списку заявок: з мінімальним верхнім відступом (5px) і з малим проміжком між заголовком і фільтром дат. --}}
    <style>
        .fi-resource-leads.fi-resource-list-records-page .fi-page-header-main-ctn { padding-top: 5px; padding-bottom: 1rem; row-gap: .5rem; }
        .fi-resource-leads.fi-resource-list-records-page .fi-page-content { row-gap: 1rem; }
    </style>

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

    @include('filament.components.date-bar')

    {{ $this->table }}

</x-filament-panels::page>
