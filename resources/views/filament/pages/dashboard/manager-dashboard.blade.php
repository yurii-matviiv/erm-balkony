<x-filament-panels::page>

    {{-- FILTERS --}}
    <div class="mb-6">

        <x-filament::section>

            {{ $this->filtersForm }}

        </x-filament::section>

    </div>

    {{-- WIDGETS --}}
    <x-filament-widgets::widgets
        :columns="$this->getHeaderWidgetsColumns()"
        :data="$this->getWidgetData()"
        :widgets="$this->getVisibleHeaderWidgets()"
    />

</x-filament-panels::page>