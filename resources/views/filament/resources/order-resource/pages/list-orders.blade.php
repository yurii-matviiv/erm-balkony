<x-filament-panels::page>


    @include('filament.components.date-bar')

    @include('filament.components.sticky-table-header')
    <div class="sticky-table-header">
        {{ $this->table }}
    </div>

</x-filament-panels::page>
