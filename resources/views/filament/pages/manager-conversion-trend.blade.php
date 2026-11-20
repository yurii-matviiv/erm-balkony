{{-- «Динаміка менеджера»: заявки, конверсія й оборот одного менеджера за вибраний період.
     Кожна точка — підсумок за останні N днів (ковзне вікно), щоб лінії були плавними хвилями, а не падали в нуль.
     Кожна лінія має власну шкалу (від її мінімуму до максимуму), бо одиниці різні. Vars: $trend, $trendName. --}}
@php
    $pts = $trend['points'];
    $n = count($pts);
    $win = $trend['window'];
    $W = 900; $H = 320; $L = 20; $R = 20; $T = 20; $B = 44;
    $iw = $W - $L - $R; $ih = $H - $T - $B;
    $x = fn (int $i) => $L + ($n > 1 ? $iw * $i / ($n - 1) : $iw / 2);
    // own scale per line, with a little air above and below so the wave never sticks to the frame
    $y = function (float $v, string $k) use ($trend, $T, $ih) {
        $lo = $trend['min'][$k]; $hi = $trend['max'][$k];
        $f = $hi > $lo ? ($v - $lo) / ($hi - $lo) : .5;

        return $T + $ih * (1 - (.06 + .88 * $f));
    };
    $series = [
        'leads' => ['Заявки', '#3b82f6', 'заявок за '.$win.' дн.', fn ($v) => (int) $v],
        'rate' => ['Конверсія', '#16a34a', 'конверсія заявок за '.$win.' дн.', fn ($v) => number_format($v, 1, ',', ' ').'%'],
        'turnover' => ['Оборот', '#d97706', 'оборот за '.$win.' дн.', fn ($v) => number_format($v, 0, '.', ' ').' ₴'],
    ];
    $r = $trend['correlation'];
    $strength = $r === null ? null : (abs($r) < 0.3 ? 'зв’язку майже немає' : (abs($r) < 0.6 ? 'помірний зв’язок' : 'сильний зв’язок'));

    // smooth curve (Catmull-Rom → cubic Bézier) through points; gaps (null) break the line
    $smooth = function (array $xy): string {
        $d = ''; $m = count($xy);
        for ($i = 0; $i < $m; $i++) {
            if ($i === 0) { $d .= 'M'.round($xy[0][0], 1).' '.round($xy[0][1], 1).' '; continue; }
            $p0 = $xy[max(0, $i - 2)]; $p1 = $xy[$i - 1]; $p2 = $xy[$i]; $p3 = $xy[min($m - 1, $i + 1)];
            $c1x = $p1[0] + ($p2[0] - $p0[0]) / 6; $c1y = $p1[1] + ($p2[1] - $p0[1]) / 6;
            $c2x = $p2[0] - ($p3[0] - $p1[0]) / 6; $c2y = $p2[1] - ($p3[1] - $p1[1]) / 6;
            $d .= 'C'.round($c1x, 1).' '.round($c1y, 1).' '.round($c2x, 1).' '.round($c2y, 1).' '.round($p2[0], 1).' '.round($p2[1], 1).' ';
        }

        return trim($d);
    };
@endphp

<div class="mc-block">
    <div class="mc-block-head">
        <div>
            <div class="mc-block-title">Динаміка менеджера: {{ $trendName }}</div>
            <div class="mc-block-desc">
                Заявки, конверсія й оборот цього менеджера за вибраний період. Кожна точка — підсумок за останні {{ $win }} днів
                (так лінії показують тенденцію, а не стрибки окремих днів і вихідних). У кожної лінії своя шкала:
                нижня частина — її найменше значення, верхня — найбільше. Наведіть курсор на графік, щоб побачити цифри за дату.
            </div>
        </div>
    </div>

    <x-filament::section>
        @if ($n < 2)
            <div class="py-6 text-center text-sm text-gray-400">Замало точок для графіка — виберіть довший період.</div>
        @else
            <style>.mc-hit { fill: transparent; } .mc-hit:hover { fill: rgba(127,127,127,.14); }</style>
            <div style="display:flex;flex-wrap:wrap;gap:1.25rem;margin-bottom:.75rem;font-size:.85rem">
                @foreach ($series as $key => [$label, $color, $unit, $fmt])
                    <div><span style="display:inline-block;width:.9rem;height:.25rem;background:{{ $color }};vertical-align:middle;margin-right:.35rem"></span><strong>{{ $label }}</strong> · {{ $unit }}: від {{ $fmt($trend['min'][$key]) }} до {{ $fmt($trend['max'][$key]) }}</div>
                @endforeach
            </div>

            <svg viewBox="0 0 {{ $W }} {{ $H }}" style="width:100%;height:auto;display:block" role="img">
                @foreach ([0, .25, .5, .75, 1] as $g)
                    <line x1="{{ $L }}" x2="{{ $W - $R }}" y1="{{ $T + $ih * $g }}" y2="{{ $T + $ih * $g }}" stroke="currentColor" stroke-opacity=".12" />
                @endforeach

                @foreach ($series as $key => [$label, $color, $unit, $fmt])
                    @php
                        // continuous runs (conversion is empty when the window has no leads)
                        $runs = []; $cur = [];
                        foreach ($pts as $i => $p) {
                            if ($p[$key] === null) { if ($cur) { $runs[] = $cur; $cur = []; } continue; }
                            $cur[] = [$x($i), $y((float) $p[$key], $key)];
                        }
                        if ($cur) { $runs[] = $cur; }
                    @endphp
                    @foreach ($runs as $run)
                        @if (count($run) > 1)
                            <path d="{{ $smooth($run) }}" fill="none" stroke="{{ $color }}" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                        @endif
                    @endforeach
                @endforeach

                {{-- one invisible column per date: hover shows all three values together --}}
                @php $colW = $iw / max(1, $n - 1); @endphp
                @foreach ($pts as $i => $p)
                    <rect class="mc-hit" x="{{ round($x($i) - $colW / 2, 1) }}" y="{{ $T }}" width="{{ round($colW, 1) }}" height="{{ $ih }}">
                        <title>{{ \Carbon\Carbon::parse($p['from'])->format('d.m') }}–{{ \Carbon\Carbon::parse($p['date'])->format('d.m') }} ({{ $win }} дн.)
Заявок: {{ $p['leads'] }}
Конверсія: {{ $p['rate'] === null ? '—' : number_format($p['rate'], 1, ',', ' ').'% ('.$p['converted'].' з '.$p['leads'].')' }}
Оборот: {{ number_format($p['turnover'], 0, '.', ' ') }} ₴</title>
                    </rect>
                @endforeach

                @php $every = max(1, (int) ceil($n / 10)); @endphp
                @foreach ($pts as $i => $p)
                    @if ($i % $every === 0)
                        <text x="{{ round($x($i), 1) }}" y="{{ $H - 18 }}" text-anchor="middle" font-size="12" fill="currentColor" fill-opacity=".65">{{ $p['label'] }}</text>
                    @endif
                @endforeach
            </svg>

            <div style="margin-top:1rem;font-size:.9rem;line-height:1.5">
                <strong>Кореляція «кількість заявок ↔ конверсія»:</strong>
                @if ($r === null)
                    <span class="mc-mid">недостатньо даних</span> — потрібно щонайменше {{ \App\Services\ManagerConversion\ManagerTrendService::MIN_POINTS }} днів із заявками та різні значення.
                @else
                    <strong class="{{ abs($r) < 0.3 ? 'mc-mid' : ($r < 0 ? 'mc-bad' : 'mc-good') }}">{{ number_format($r, 2, ',', '') }}</strong> — {{ $strength }} (за {{ $trend['corrPoints'] }} днями із заявками).
                    @if (abs($r) >= 0.3)
                        {{ $r < 0 ? 'Чим більше заявок за день, тим нижча конверсія: схоже, що менеджер не встигає опрацьовувати потік.' : 'Чим більше заявок за день, тим вища конверсія: навантаження йому не шкодить.' }}
                    @endif
                @endif
                <div style="opacity:.65;font-size:.8rem">Шкала від −1 до +1: біля 0 зв’язку немає, до −1 — більше заявок знижує конверсію, до +1 — підвищує. Рахується по окремих днях (без згладжування). Це лише зв’язок у цих даних, не доказ причини. Конверсія останніх днів ще зростатиме: замовлення з’являються із запізненням.</div>
            </div>
        @endif
    </x-filament::section>
</div>
