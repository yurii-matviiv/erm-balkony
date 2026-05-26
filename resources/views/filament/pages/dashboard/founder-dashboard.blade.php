{{-- 
---------------------------------------------------------
STACK / PROJECT STANDARD
---------------------------------------------------------
Laravel 13.11.2
Livewire 3.8.0
Filament 4.11.5
Blade + Alpine.js
---------------------------------------------------------
--}}

<x-filament-panels::page>

    {{-- 
    ---------------------------------------------------------
    FILTERS
    ---------------------------------------------------------
    --}}
    <x-filament::section class="mb-6">

        <div class="flex flex-col gap-4">

            <div class="w-full max-w-md">

                {{ $this->filtersForm }}

            </div>

            {{-- 
            ---------------------------------------------------------
            LOADING
            ---------------------------------------------------------
            --}}
            <div
                wire:loading.flex
                wire:target="filters"
                class="items-center gap-2 text-sm text-warning-600"
            >

                <x-filament::loading-indicator class="h-5 w-5" />

                <span>Оновлення даних...</span>

            </div>

        </div>

    </x-filament::section>

    {{-- 
    ---------------------------------------------------------
    WIDGETS
    ---------------------------------------------------------
    --}}
    <div
        wire:loading.class="opacity-50"
        wire:target="filters"
        class="transition duration-300"
        x-data="{ refreshKey: 0 }"
        x-on:refresh-widgets.window="refreshKey++; $wire.$refresh()"
    >

        <x-filament-widgets::widgets
            :columns="2"
            :widgets="$this->getWidgets()"
            x-bind:key="refreshKey"
        />

    </div>

    <script>
        document.addEventListener('livewire:initialized', () => {
            Livewire.on('refresh-widgets', () => {
                window.dispatchEvent(new CustomEvent('refresh-widgets'));
            });
        });
    </script>

</x-filament-panels::page>