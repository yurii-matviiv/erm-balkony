<x-filament-panels::page>

    <x-filament::section heading="Для якої ролі налаштовуємо?">
        <select
            wire:model.live="role"
            class="fi-select-input block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm"
        >
            @foreach ($this->getRoleOptions() as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
            "Активна структура" нижче — точна копія того, що ця роль бачить у боковій панелі. "Вимкнені пункти" — окремо внизу, щоб не шукати активні серед вимкнених. Стрілки і назви зберігаються одразу. Перемикач "Активний" — лише на цій сторінці, поки не натиснеш "Застосувати зміни".
        </p>
    </x-filament::section>

    @if ($this->hasPendingChanges())
        <div class="flex items-center gap-3 rounded-lg bg-amber-50 dark:bg-amber-950 border border-amber-200 dark:border-amber-800 p-4" wire:key="pending-banner">
            <span class="text-sm text-amber-800 dark:text-amber-200">Є незастосовані зміни активності пунктів.</span>
            <x-filament::button wire:click="applyActiveChanges" size="sm">
                Застосувати зміни
            </x-filament::button>
        </div>
    @endif

    <h2 class="text-base font-semibold">Активна структура (як у боковій панелі)</h2>

    {{--
        IMPORTANT: item keys are PHP class names like
        "App\Filament\Resources\LeadResource" — full of backslashes.
        Interpolating them directly as '{{ $key }}' inside a wire:click
        attribute breaks them: wire:click is parsed as a JS-like
        expression, and JS silently drops backslashes before characters
        that aren't recognized escape sequences (\F, \R, \S, ...), turning
        the key into "AppFilamentResourcesLeadResource" — which then
        matches nothing server-side. `Js::from()` properly escapes the
        string for safe embedding in a JS context, so the real key
        (including backslashes) survives. This was the actual cause of
        "clicking the arrows does nothing" — confirmed by checking the
        database, where a mangled no-backslash key showed up.
    --}}

    <div class="space-y-4">
        @forelse ($this->getActiveGroupedItems() as $group)
            @php($firstItemKey = $group['items']->first()['key'] ?? null)

            <div class="rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden" wire:key="active-group-{{ $group['label'] }}">

                {{-- Group header: name (editable) + arrows that move the WHOLE group --}}
                <div class="flex items-center gap-2 bg-gray-50 dark:bg-gray-800 p-3">
                    <button
                        type="button"
                        wire:click="moveGroup({{ \Illuminate\Support\Js::from($firstItemKey) }}, -1)"
                        title="Група вище"
                        class="rounded p-1 hover:bg-gray-200 dark:hover:bg-gray-700"
                    >
                        <x-filament::icon icon="heroicon-o-chevron-up" class="h-4 w-4" />
                    </button>
                    <button
                        type="button"
                        wire:click="moveGroup({{ \Illuminate\Support\Js::from($firstItemKey) }}, 1)"
                        title="Група нижче"
                        class="rounded p-1 hover:bg-gray-200 dark:hover:bg-gray-700"
                    >
                        <x-filament::icon icon="heroicon-o-chevron-down" class="h-4 w-4" />
                    </button>

                    <input
                        type="text"
                        value="{{ $group['label'] }}"
                        wire:change="renameGroup({{ \Illuminate\Support\Js::from($firstItemKey) }}, $event.target.value)"
                        class="fi-input flex-1 max-w-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm font-semibold"
                    />
                </div>

                {{-- Items in this group, indented to read as "belonging to" the header above --}}
                <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach ($group['items'] as $item)
                        <li
                            class="flex items-center gap-2 p-3 pl-8"
                            wire:key="active-item-{{ $item['key'] }}"
                        >
                            <button
                                type="button"
                                wire:click="moveItem({{ \Illuminate\Support\Js::from($item['key']) }}, -1)"
                                title="Пункт вище"
                                class="rounded p-1 hover:bg-gray-100 dark:hover:bg-gray-800"
                            >
                                <x-filament::icon icon="heroicon-o-arrow-up" class="h-4 w-4" />
                            </button>
                            <button
                                type="button"
                                wire:click="moveItem({{ \Illuminate\Support\Js::from($item['key']) }}, 1)"
                                title="Пункт нижче"
                                class="rounded p-1 hover:bg-gray-100 dark:hover:bg-gray-800"
                            >
                                <x-filament::icon icon="heroicon-o-arrow-down" class="h-4 w-4" />
                            </button>

                            <x-filament::icon :icon="$item['icon'] ?? 'heroicon-o-square'" class="h-4 w-4 text-gray-400" />

                            <input
                                type="text"
                                value="{{ $item['label'] }}"
                                wire:change="saveLabel({{ \Illuminate\Support\Js::from($item['key']) }}, $event.target.value)"
                                class="fi-input flex-1 max-w-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm"
                            />

                            <label class="ml-auto inline-flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                                Активний
                                <input
                                    type="checkbox"
                                    checked
                                    wire:click="toggleActive({{ \Illuminate\Support\Js::from($item['key']) }})"
                                    class="fi-checkbox-input rounded"
                                />
                            </label>
                        </li>
                    @endforeach
                </ul>
            </div>
        @empty
            <p class="text-sm text-gray-400">Для цієї ролі немає жодного активного пункту меню.</p>
        @endforelse
    </div>

    @if ($this->getInactiveItems()->isNotEmpty())
        <h2 class="text-base font-semibold mt-6">Вимкнені пункти</h2>

        <div class="rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
            <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                @foreach ($this->getInactiveItems() as $item)
                    <li
                        class="flex items-center gap-2 p-3 opacity-60"
                        wire:key="inactive-item-{{ $item['key'] }}"
                    >
                        <button
                            type="button"
                            wire:click="moveItem({{ \Illuminate\Support\Js::from($item['key']) }}, -1)"
                            title="Пункт вище"
                            class="rounded p-1 hover:bg-gray-100 dark:hover:bg-gray-800"
                        >
                            <x-filament::icon icon="heroicon-o-arrow-up" class="h-4 w-4" />
                        </button>
                        <button
                            type="button"
                            wire:click="moveItem({{ \Illuminate\Support\Js::from($item['key']) }}, 1)"
                            title="Пункт нижче"
                            class="rounded p-1 hover:bg-gray-100 dark:hover:bg-gray-800"
                        >
                            <x-filament::icon icon="heroicon-o-arrow-down" class="h-4 w-4" />
                        </button>

                        <x-filament::icon :icon="$item['icon'] ?? 'heroicon-o-square'" class="h-4 w-4 text-gray-400" />

                        <span class="text-xs text-gray-400 whitespace-nowrap">{{ $item['group'] }} /</span>

                        <input
                            type="text"
                            value="{{ $item['label'] }}"
                            wire:change="saveLabel({{ \Illuminate\Support\Js::from($item['key']) }}, $event.target.value)"
                            class="fi-input flex-1 max-w-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm"
                        />

                        <label class="ml-auto inline-flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                            Активний
                            <input
                                type="checkbox"
                                wire:click="toggleActive({{ \Illuminate\Support\Js::from($item['key']) }})"
                                class="fi-checkbox-input rounded"
                            />
                        </label>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

</x-filament-panels::page>
