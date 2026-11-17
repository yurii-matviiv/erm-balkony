{{-- Бар індикаторів над таблицею лідів. Кожен показник — окрема плитка; нові індикатори додавати в $items нижче. --}}
@php
    $userId = auth()->id();
    $reaction = $userId ? \App\Services\Leads\ManagerIndicators::reaction($userId) : null;
    $days = \App\Services\Leads\ManagerIndicators::DAYS;

    $items = [];
    if ($reaction) {
        $items[] = [
            'label' => 'Швидкість реакції (за '.$days.' днів)',
            'value' => $reaction['avg'] !== null ? \App\Services\Leads\ManagerIndicators::formatMinutes($reaction['avg']) : '—',
            'color' => $reaction['avg'] !== null ? \App\Services\Leads\ManagerIndicators::reactionColor($reaction['avg']) : '#6b7280',
            'note' => $reaction['avg'] !== null
                ? 'у розрахунку '.$reaction['counted'].' заявок'.($reaction['without'] > 0 ? ', без дзвінка ще '.$reaction['without'] : '')
                : 'ще немає заявок із дзвінком',
        ];
    }
@endphp
@if ($items)
    <div style="display:flex; flex-wrap:wrap; gap:12px; margin-bottom:12px;">
        @foreach ($items as $item)
            <div style="border:1px solid rgb(0 0 0 / .1); border-radius:3px; padding:8px 16px; min-width:220px; background:#fff;">
                <div style="font-size:.75rem; color:#6b7280;">{{ $item['label'] }}</div>
                <div style="font-size:1.375rem; font-weight:700; line-height:1.9rem; color:{{ $item['color'] }};">{{ $item['value'] }}</div>
                <div style="font-size:.75rem; color:#6b7280;">{{ $item['note'] }}</div>
            </div>
        @endforeach
    </div>
@endif
