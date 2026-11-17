{{-- Бар індикаторів у шапці сторінки лідів (в одному рядку із заголовком, праворуч; ПЕРШИЙ індикатор — крайній правий, row-reverse). Підключено хуком у AppServiceProvider. Кожен показник — окрема плитка; нові індикатори додавати в $items нижче. --}}
@php
    $userId = auth()->id();
    $reaction = $userId ? \App\Services\Leads\ManagerIndicators::reaction($userId) : null;
    $days = \App\Services\Leads\ManagerIndicators::DAYS;

    $items = [];
    if ($reaction) {
        $items[] = [
            'label' => 'Швидкість реакції',
            'hint' => 'Середній час від створення заявки до першого дзвінка клієнту за останні '.$days.' днів. '
                .'Рахуються лише заявки, у яких дзвінок уже був; заявки без дзвінка в середнє не входять. '
                .'Якщо дзвінок був раніше за створення заявки в системі — це 0 хв.',
            'value' => $reaction['avg'] !== null ? \App\Services\Leads\ManagerIndicators::formatMinutes($reaction['avg']) : '—',
            'color' => $reaction['avg'] !== null ? \App\Services\Leads\ManagerIndicators::reactionColor($reaction['avg']) : '#6b7280',
            'note' => $reaction['avg'] !== null
                ? 'у розрахунку '.$reaction['counted'].' заявок'.($reaction['without'] > 0 ? ', без дзвінка ще '.$reaction['without'] : '')
                : 'ще немає заявок із дзвінком',
        ];
    }
@endphp
@if ($items)
    <div style="display:flex; flex-direction:row-reverse; flex-wrap:wrap; justify-content:flex-start; gap:12px;">
        @foreach ($items as $item)
            <div style="border:1px solid rgb(0 0 0 / .1); border-radius:3px; padding:8px 16px; min-width:220px; background:#fff;">
                <div style="font-size:.75rem; color:#6b7280; display:flex; align-items:center; gap:4px;">
                    <span>{{ $item['label'] }}</span>
                    @if (! empty($item['hint']))
                        <span x-data x-tooltip="{ content: @js($item['hint']), theme: $store.theme }" style="display:inline-flex; cursor:help;">
                            <x-filament::icon icon="heroicon-o-information-circle" style="width:16px; height:16px;" />
                        </span>
                    @endif
                </div>
                <div style="font-size:1.375rem; font-weight:700; line-height:1.9rem; color:{{ $item['color'] }};">{{ $item['value'] }}</div>
                <div style="font-size:.75rem; color:#6b7280;">{{ $item['note'] }}</div>
            </div>
        @endforeach
    </div>
@endif
