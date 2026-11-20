{{-- Manager picker. Normal mode: checkboxes (several managers). Compare mode: radio buttons (exactly one manager).
     Vars: $compare, $managerOptions, $selectedManagers, $compareData. --}}
<div class="flex flex-col gap-1" x-data="{ open: false, top: 0, left: 0 }" @click.outside="open = false" @keydown.escape.window="open = false">
    <label class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $compare ? 'Менеджер' : 'Менеджери' }}</label>
    <button type="button" x-ref="btn"
            @click="const r = $refs.btn.getBoundingClientRect(); top = r.bottom + 4; left = r.left; open = ! open"
            class="flex items-center justify-between gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
            style="min-width:12rem">
        <span>
            @if ($compare)
                {{ implode(' ', array_slice(explode(' ', $compareData['options'][$compareData['managerId']] ?? '—'), 0, 2)) }}
            @elseif (count($selectedManagers) === 0)
                Усі менеджери
            @elseif (count($selectedManagers) <= 2)
                {{ collect($selectedManagers)->map(fn ($n) => implode(' ', array_slice(explode(' ', $n), 0, 2)))->implode(', ') }}
            @else
                Вибрано: {{ count($selectedManagers) }}
            @endif
        </span>
        <span aria-hidden="true">▾</span>
    </button>
    {{-- position:fixed so the list is not clipped by the card around the filter --}}
    <div x-show="open" x-cloak :style="`position:fixed;top:${top}px;left:${left}px;z-index:60;min-width:16rem`"
         class="rounded-lg border border-gray-300 bg-white p-2 shadow-lg dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
        @if ($compare)
            @foreach ($compareData['options'] as $id => $name)
                <label class="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-sm hover:bg-gray-100 dark:hover:bg-gray-700">
                    <input type="radio" value="{{ $id }}" wire:model.live="compareManagerId" />
                    {{ $name }}
                </label>
            @endforeach
        @else
            <button type="button" wire:click="$set('managerIds', [])" class="w-full rounded px-2 py-1.5 text-left text-sm hover:bg-gray-100 dark:hover:bg-gray-700">
                <strong>Усі менеджери</strong> (зняти вибір)
            </button>
            @foreach ($managerOptions as $id => $name)
                <label class="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-sm hover:bg-gray-100 dark:hover:bg-gray-700">
                    <input type="checkbox" value="{{ $id }}" wire:model.live="managerIds" class="rounded border-gray-300" />
                    {{ $name }}
                </label>
            @endforeach
        @endif
    </div>
</div>
