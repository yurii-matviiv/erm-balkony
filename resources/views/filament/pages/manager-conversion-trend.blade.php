{{-- «Динаміка менеджера»: заявки, конверсія й оборот одного менеджера за вибраний період.
     Кожна лінія має власну шкалу (0 → її максимум), бо одиниці різні. Vars: $trend, $trendName. --}}
@php
    $pts = $trend['points'];
    $n = count($pts);
    $W = 900; $H = 300; $L = 20; $R = 20; $T = 20; $B = 44;
    $iw = $W - $L - $R; $ih = $H - $T - $B;
    $x = fn (int $i) => $L + ($n > 1 ? $iw * $i / ($n - 1) : $iw / 2);
    $y = fn (float $v, float $max) => $T + $ih - ($max > 0 ? $ih * $v / $max : 0);
    $series = [
        'leads' => ['Заявки', '#3b82f6', $trend['maxLeads'], fn ($p) => (float) $p['leads'], fn ($v) => (int) $v.' заявок'],
        'rate' => ['Конверсія', '#16a34a', $trend['maxRate'], fn ($p) => $p['rate'], fn ($v) => number_format($v, 1, ',', ' ').'%'],
        'turnover' => ['Оборот', '#d97706', $trend['maxTurnover'], fn ($p) => (float) $p['turnover'], fn ($v) => number_format($v, 0, '.', ' ').' ₴'],
    ];
    $r = $trend['correlation'];
    $strength = $r === null ? null : (abs($r) < 0.3 ? 'зв’язку майже немає' : (abs($r) < 0.6 ? 'помірний зв’язок' : 'сильний зв’язок'));
@endphp

<div class="mc-block">
    <div class="mc-block-head">
        <div>
            <div class="mc-block-title">Динаміка менеджера: {{ $trendName }}</div>
            <div class="mc-block-desc">
                Заявки, конверсія й оборот цього менеджера за вибраний період ({{ $trend['stepLabel'] }}).
                У кожної лінії своя шкала: найвища точка — це максимум саме цієї лінії, а не спільна цифра.
                Наведіть курсор на точку, щоб побачити значення.
            </div>
        </div>
    </div>

    <x-filament::section>
        @if ($n < 2)
            <div class="py-6 text-center text-sm text-gray-400">Замало точок для графіка — виберіть довший період.</div>
        @else
            <div style="display:flex;flex-wrap:wrap;gap:1.25rem;margin-bottom:.75rem;font-size:.85rem">
                @foreach ($series as $key => [$label, $color, $max, $get, $fmt])
                    <div><span style="display:inline-block;width:.9rem;height:.25rem;background:{{ $color }};vertical-align:middle;margin-right:.35rem"></span><strong>{{ $label }}</strong> · максимум {{ $fmt($max) }}</div>
                @endforeach
            </div>

            <svg viewBox="0 0 {{ $W }} {{ $H }}" style="width:100%;height:auto;display:block" role="img">
                @foreach ([0, .25, .5, .75, 1] as $g)
                    <line x1="{{ $L }}" x2="{{ $W - $R }}" y1="{{ $T + $ih * $g }}" y2="{{ $T + $ih * $g }}" stroke="currentColor" stroke-opacity=".12" />
                @endforeach

                @foreach ($series as $key => [$label, $color, $max, $get, $fmt])
                    @php
                        $path = '';
                        $pen = false;
                        foreach ($pts as $i => $p) {
                            $v = $get($p);
                            if ($v === null) { $pen = false; continue; }
                            $path .= ($pen ? 'L' : 'M').round($x($i), 1).' '.round($y($v, $max), 1).' ';
                            $pen = true;
                        }
                    @endphp
                    <path d="{{ trim($path) }}" fill="none" stroke="{{ $color }}" stroke-width="2.5" stroke-linejoin="round" />
                    @foreach ($pts as $i => $p)
                        @php $v = $get($p); @endphp
                        @if ($v !== null)
                            <circle cx="{{ round($x($i), 1) }}" cy="{{ round($y($v, $max), 1) }}" r="4" fill="{{ $color }}">
                                <title>{{ $p['label'] }} · {{ $label }}: {{ $fmt($v) }}@if ($key === 'rate') ({{ $p['converted'] }} з {{ $p['leads'] }} заявок)@endif</title>
                            </circle>
                        @endif
                    @endforeach
                @endforeach

                @php $every = max(1, (int) ceil($n / 12)); @endphp
                @foreach ($pts as $i => $p)
                    @if ($i % $every === 0)
                        <text x="{{ round($x($i), 1) }}" y="{{ $H - 18 }}" text-anchor="middle" font-size="12" fill="currentColor" fill-opacity=".65">{{ $p['label'] }}</text>
                    @endif
                @endforeach
            </svg>

            <div style="margin-top:1rem;font-size:.9rem;line-height:1.5">
                <strong>Кореляція «кількість заявок ↔ конверсія»:</strong>
                @if ($r === null)
                    <span class="mc-mid">недостатньо даних</span> — потрібно щонайменше {{ \App\Services\ManagerConversion\ManagerTrendService::MIN_POINTS }} точок із заявками та різні значення.
                @else
                    <strong class="{{ abs($r) < 0.3 ? 'mc-mid' : ($r < 0 ? 'mc-bad' : 'mc-good') }}">{{ number_format($r, 2, ',', '') }}</strong> — {{ $strength }}.
                    @if (abs($r) >= 0.3)
                        {{ $r < 0 ? 'Чим більше заявок за '.($trend['step'] === 'day' ? 'день' : ($trend['step'] === 'week' ? 'тиждень' : 'місяць')).', тим нижча конверсія: схоже, що менеджер не встигає опрацьовувати потік.' : 'Чим більше заявок, тим вища конверсія: навантаження йому не шкодить.' }}
                    @endif
                    <div style="opacity:.65;font-size:.8rem">Шкала від −1 до +1: біля 0 зв’язку немає, до −1 — більше заявок знижує конверсію, до +1 — підвищує. Це лише зв’язок у цих даних, не доказ причини.</div>
                @endif
            </div>
        @endif
    </x-filament::section>
</div>
