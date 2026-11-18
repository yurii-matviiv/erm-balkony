@php
    $cards = $this->getCards();
    $plural = function (int $n): string {
        $m10 = $n % 10; $m100 = $n % 100;
        if ($m10 === 1 && $m100 !== 11) return 'відкриття';
        if ($m10 >= 2 && $m10 <= 4 && ($m100 < 12 || $m100 > 14)) return 'відкриття';
        return 'відкриттів';
    };
@endphp
<x-filament-panels::page>
    <style>
        .pop-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(210px,1fr)); gap:16px; }
        .pop-card {
            position:relative; display:flex; flex-direction:column; gap:14px; min-height:170px; padding:18px;
            border:1px solid var(--gray-200); background:#fff; color:inherit; text-decoration:none;
            transition:transform .15s ease, box-shadow .15s ease, border-color .15s ease;
        }
        .pop-card:hover { transform:translateY(-3px); border-color:var(--primary-400); box-shadow:0 8px 22px rgb(0 0 0 / .10); }
        .pop-card:hover .pop-icon { background:var(--primary-600); color:#fff; }
        .pop-card:focus-visible { outline:2px solid var(--primary-600); outline-offset:2px; }
        .pop-rank { position:absolute; top:0; right:0; padding:3px 10px; font-size:12px; font-weight:600; background:var(--gray-100); color:var(--gray-600); }
        .pop-card.is-first { border-color:var(--primary-300); }
        .pop-card.is-first .pop-rank { background:var(--primary-600); color:#fff; }
        .pop-icon { flex:none; width:48px; height:48px; display:flex; align-items:center; justify-content:center; background:var(--primary-100); color:var(--primary-700); transition:background .15s ease, color .15s ease; }
        .pop-label { font-size:16px; font-weight:600; line-height:1.25; color:var(--gray-950); }
        .pop-meta { margin-top:auto; font-size:12px; color:var(--gray-500); }
        .pop-count { font-size:13px; font-weight:600; color:var(--primary-700); }
        .dark .pop-card { background:var(--gray-900); border-color:rgb(255 255 255 / .12); }
        .dark .pop-label { color:#fff; }
        .dark .pop-rank { background:var(--gray-800); color:var(--gray-300); }
        .dark .pop-icon { background:var(--gray-800); color:var(--primary-300); }
    </style>

    @if (count($cards) === 0)
        <p class="text-sm text-gray-500">У вашому меню поки немає сторінок. Їх можна ввімкнути в «Бокова панель».</p>
    @else
        <p class="text-sm text-gray-500" style="margin-bottom:4px;">Ваші найчастіші сторінки: першою стоїть та, яку ви відкриваєте найбільше.</p>
        <div class="pop-grid">
            @foreach ($cards as $card)
                <a href="{{ $card['url'] }}" class="pop-card {{ $card['rank'] === 1 ? 'is-first' : '' }}">
                    <span class="pop-rank">#{{ $card['rank'] }}</span>
                    <span class="pop-icon"><x-filament::icon :icon="$card['icon']" style="width:26px;height:26px;" /></span>
                    <span class="pop-label">{{ $card['label'] }}</span>
                    <span class="pop-meta">
                        @if ($card['visits'] > 0)
                            <span class="pop-count">{{ $card['visits'] }} {{ $plural($card['visits']) }}</span>
                            <br>остання: {{ $card['last_visited_at'] }}
                        @else
                            ще не відкривалась
                        @endif
                    </span>
                </a>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
