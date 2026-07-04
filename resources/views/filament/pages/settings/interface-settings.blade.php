<x-filament-panels::page>

    <x-filament::section>
        <a
            href="{{ \App\Filament\Pages\Settings\SidebarSettings::getUrl() }}"
            class="flex items-center gap-3 rounded-lg border border-gray-200 dark:border-gray-700 p-4 hover:bg-gray-50 dark:hover:bg-gray-800 transition"
        >
            <x-filament::icon icon="heroicon-o-bars-3" class="h-6 w-6 text-gray-400" />
            <span>
                <span class="block font-medium">Бокова панель</span>
                <span class="block text-sm text-gray-500 dark:text-gray-400">Налаштувати, які пункти меню бачить кожна роль, і в якому порядку.</span>
            </span>
        </a>
    </x-filament::section>

</x-filament-panels::page>
