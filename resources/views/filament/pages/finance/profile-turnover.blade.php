@php
    // Українські числа: пробіл між тисячами, кома в десяткових.
    $money = fn (float $v): string => number_format($v, 0, ',', ' ') . ' ₴';
    $area  = fn (float $v): string => number_format($v, 1, ',', ' ') . ' м²';

    // Інлайн-стилі навмисно: Tailwind-збірка проєкту не містить довільних утиліт (на хостингу немає node),
    // а сітка/смужки тут потрібні адаптивні. Кольори — зі змінних теми Filament (працює й у темній).
    $grid = fn (string $min): string => "display:grid;grid-template-columns:repeat(auto-fit,minmax({$min},1fr));gap:1rem;";
    $cardBase = 'background:var(--color-white,#fff);border:1px solid rgba(128,128,128,.22);border-radius:6px;';
    $label = 'font-size:.7rem;font-weight:600;letter-spacing:.06em;text-transform:uppercase;opacity:.6;';
@endphp
<x-filament-panels::page>

    {{-- 1. Період (стандартний бар дат, docs/ui/date-filter.md) --}}
    @include('filament.components.date-bar')

    {{-- 2. Ключові показники: головне, що видно одразу після вибору періоду --}}
    <div style="{{ $grid('15rem') }}">
        @foreach ([
            ['icon' => 'heroicon-o-banknotes', 'label' => 'Вартість постачальників', 'value' => $money($total['sum']), 'sub' => $total['payments'] . ' виплат', 'hero' => true],
            ['icon' => 'heroicon-o-clipboard-document-check', 'label' => 'Замовлень', 'value' => number_format($total['orders'], 0, ',', ' '), 'sub' => 'унікальних, по яких платили', 'hero' => false],
            ['icon' => 'heroicon-o-square-3-stack-3d', 'label' => 'Продано', 'value' => $area($total['area']), 'sub' => 'за площею цих замовлень', 'hero' => false],
            ['icon' => 'heroicon-o-calculator', 'label' => 'Оборот на 1 м²', 'value' => $total['per_m2'] ? $money($total['per_m2']) : '—', 'sub' => 'оборот ÷ площа', 'hero' => false],
        ] as $k)
            <div class="bg-white dark:bg-gray-900" style="border:1px solid rgba(128,128,128,.22);border-radius:6px;padding:1.1rem 1.25rem;{{ $k['hero'] ? 'border-left:4px solid var(--primary-500);' : '' }}">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:.5rem;">
                    <span style="{{ $label }}" class="text-gray-950 dark:text-white">{{ $k['label'] }}</span>
                    <span style="width:2rem;height:2rem;border-radius:6px;display:inline-flex;align-items:center;justify-content:center;background:color-mix(in srgb, var(--primary-500) 12%, transparent);color:var(--primary-600);">
                        <x-filament::icon :icon="$k['icon']" class="h-5 w-5" />
                    </span>
                </div>
                <div style="font-size:2rem;line-height:1.15;font-weight:700;margin-top:.55rem;{{ $k['hero'] ? 'color:var(--primary-600);' : '' }}" class="{{ $k['hero'] ? '' : 'text-gray-950 dark:text-white' }}">{{ $k['value'] }}</div>
                <div style="font-size:.75rem;margin-top:.25rem;" class="text-gray-500 dark:text-gray-400">{{ $k['sub'] }}</div>
            </div>
        @endforeach
    </div>

    {{-- 3. Постачальники: частка, оборот і м² по кожному; клік вибирає (порожньо = усі) --}}
    <x-filament::section>
        <x-slot name="heading">По профільних системах</x-slot>
        <x-slot name="description">Натисніть на профіль, щоб побачити показники й виплати лише по ньому</x-slot>
        <x-slot name="afterHeader">
            @if ($selected)
                <x-filament::link tag="button" wire:click="clearProfiles" size="sm">Показати всіх</x-filament::link>
            @endif
        </x-slot>

        @if (empty($profiles))
            <div class="text-sm text-gray-500 dark:text-gray-400">За цей період виплат по профілях немає. Змініть період.</div>
        @else
            <div style="{{ $grid('17rem') }}">
                @foreach ($profiles as $s)
                    @php $isOn = in_array($s['key'], $selected, true); @endphp
                    <button type="button" wire:click="toggleProfile(@js($s['key']))" wire:key="prof-{{ $s['key'] }}"
                            class="bg-white dark:bg-gray-900"
                            style="text-align:left;cursor:pointer;padding:1rem 1.1rem;border-radius:6px;transition:border-color .15s, box-shadow .15s;
                                   border:1px solid {{ $isOn ? 'var(--primary-500)' : 'rgba(128,128,128,.22)' }};
                                   {{ $isOn ? 'box-shadow:0 0 0 1px var(--primary-500);background:color-mix(in srgb, var(--primary-500) 6%, transparent);' : '' }}">
                        <div style="display:flex;justify-content:space-between;align-items:baseline;gap:.75rem;">
                            <span style="font-size:.875rem;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" class="text-gray-950 dark:text-white" title="{{ $s['name'] }}">{{ $s['name'] }}</span>
                            <span style="font-size:.75rem;font-weight:600;white-space:nowrap;color:var(--primary-600);">{{ number_format($s['share'], 1, ',', ' ') }}%</span>
                        </div>

                        <div style="font-size:1.5rem;font-weight:700;margin-top:.35rem;" class="text-gray-950 dark:text-white">{{ $money($s['sum']) }}</div>

                        {{-- частка від обороту за період --}}
                        <div style="height:4px;border-radius:2px;margin:.6rem 0 .75rem;background:rgba(128,128,128,.2);overflow:hidden;">
                            <div style="height:100%;width:{{ min(100, round($s['share'], 1)) }}%;background:var(--primary-500);border-radius:2px;"></div>
                        </div>

                        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:.5rem;">
                            <div>
                                <div style="font-size:.65rem;text-transform:uppercase;letter-spacing:.05em;opacity:.55;" class="text-gray-950 dark:text-white">Замовлень</div>
                                <div style="font-size:.9rem;font-weight:600;" class="text-gray-950 dark:text-white">{{ $s['orders'] }}</div>
                            </div>
                            <div>
                                <div style="font-size:.65rem;text-transform:uppercase;letter-spacing:.05em;opacity:.55;" class="text-gray-950 dark:text-white">Площа</div>
                                <div style="font-size:.9rem;font-weight:600;" class="text-gray-950 dark:text-white">{{ $area($s['area']) }}</div>
                            </div>
                            <div>
                                <div style="font-size:.65rem;text-transform:uppercase;letter-spacing:.05em;opacity:.55;" class="text-gray-950 dark:text-white">За 1 м²</div>
                                <div style="font-size:.9rem;font-weight:600;" class="text-gray-950 dark:text-white">{{ $s['per_m2'] ? $money($s['per_m2']) : '—' }}</div>
                            </div>
                        </div>
                    </button>
                @endforeach
            </div>
        @endif
    </x-filament::section>

    {{-- 4. Деталі: усі виплати (за замовчуванням згорнуто) --}}
    <x-filament::section collapsible collapsed heading="Деталі виплат" description="Кожна виплата окремо: дата, замовлення, №, сума">
        {{ $this->table }}
    </x-filament::section>

</x-filament-panels::page>
