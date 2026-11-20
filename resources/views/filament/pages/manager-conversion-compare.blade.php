        @php
            $A = $compareData['a'];
            $B = $compareData['b'];
            $fmtDates = fn (array $p) => \Carbon\Carbon::parse($p[0])->format('d.m.Y').' — '.\Carbon\Carbon::parse($p[1])->format('d.m.Y');
            $money = fn ($v) => number_format((float) $v, 0, '.', ' ').' ₴';
            $pct = fn ($v) => $v === null ? '—' : number_format((float) $v, 1, ',', ' ').'%';
            $int = fn ($v) => number_format((float) $v, 0, '.', ' ');
            $dec = fn ($v) => $v === null ? '—' : number_format((float) $v, 1, ',', ' ');
            $pA = $compareData['phoneA'];
            $pB = $compareData['phoneB'];
            // [label, value 1, value 2, formatter, better: up|down|null, is percent value]
            $rows = [
                ['Заявок', $A['leads'], $B['leads'], $int, 'up', false],
                ['У замовлення (заявки, що стали замовленням)', $A['converted'], $B['converted'], $int, 'up', false],
                ['Конверсія', $A['rate'], $B['rate'], $pct, 'up', true],
                ['Замовлень створено', $A['orders'], $B['orders'], $int, 'up', false],
                ['Оборот', $A['turnover'], $B['turnover'], $money, 'up', false],
                ['Витрати на рекламу для менеджера', $A['ad_cost'], $B['ad_cost'], $money, 'down', false],
                ['Витрати на рекламу, % від обороту', $A['drr'], $B['drr'], $pct, 'down', true],
                ['Результат (бюджет − витрати)', $A['result'], $B['result'], $money, 'up', false],
                ['Заявок на робочий день', $A['per_day'], $B['per_day'], $dec, null, false],
                ['Дзвінків на день (Binotel)', $pA['calls_per_day'] ?? null, $pB['calls_per_day'] ?? null, $dec, null, false],
                ['Годин розмов на день (Binotel)', $pA['hours_per_day'] ?? null, $pB['hours_per_day'] ?? null, $dec, null, false],
            ];
        @endphp

        {{-- Phone figures load lazily for both periods (cached, see BinotelCallLoadService). --}}
        <div wire:key="cmp-b1-{{ $binotelKey }}" wire:init="loadBinotel"></div>
        <div wire:key="cmp-b2-{{ $binotelKey2 }}" wire:init="loadBinotel2"></div>

        <div class="mc-block mc-block-plain">
            <div class="mc-block-head" style="margin-bottom:0">
                <div>
                    <div class="mc-block-title">Порівняння періодів — {{ $A['name'] }}</div>
                    <div class="mc-block-desc">
                        Ті самі показники одного менеджера за два періоди, від ранішого до пізнішого. «Зміна» — як змінився пізніший період
                        порівняно з ранішим: зелений колір — краще, червоний — гірше.
                    </div>
                </div>
            </div>
        </div>

        <x-filament::section>
            <div style="overflow-x:auto">
                <table style="width:100%;font-size:.9rem;border-collapse:collapse">
                    <thead>
                        <tr style="text-align:right;border-bottom:2px solid rgba(127,127,127,.45)">
                            <th style="text-align:left;padding:.6rem .75rem;font-weight:700">Показник</th>
                            <th style="padding:.6rem .75rem;font-weight:700">Раніший період<small style="display:block;font-weight:400">{{ $fmtDates($compareData['period1']) }}</small></th>
                            <th style="padding:.6rem .75rem;font-weight:700">Пізніший період<small style="display:block;font-weight:400">{{ $fmtDates($compareData['period2']) }}</small></th>
                            <th style="padding:.6rem .75rem;font-weight:700">Зміна</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as [$label, $v1, $v2, $f, $better, $isPct])
                            @php
                                $phoneRow = str_contains($label, 'Binotel');
                                $loadingPhone = $phoneRow && $v1 === null && $v2 === null;
                                // Change = later period (v2) compared with the earlier one (v1).
                                $diff = ($v1 !== null && $v2 !== null) ? $v2 - $v1 : null;
                                $rel = ($diff !== null && ! $isPct && (float) $v1 != 0.0) ? $diff / $v1 * 100 : null;
                                $cls = ($diff === null || $diff == 0 || $better === null) ? '' : ((($diff > 0) === ($better === 'up')) ? 'mc-good' : 'mc-bad');
                            @endphp
                            <tr style="border-bottom:1px solid rgba(127,127,127,.25);text-align:right">
                                <td style="text-align:left;padding:.65rem .75rem">{{ $label }}</td>
                                <td style="padding:.65rem .75rem"><strong>{{ $loadingPhone ? '…' : $f($v1) }}</strong></td>
                                <td style="padding:.65rem .75rem"><strong>{{ $loadingPhone ? '…' : $f($v2) }}</strong></td>
                                <td style="padding:.65rem .75rem" class="{{ $cls }}">
                                    @if ($diff === null)
                                        —
                                    @else
                                        <strong>{{ $diff > 0 ? '▲ +' : ($diff < 0 ? '▼ −' : '') }}{{ $isPct ? number_format(abs($diff), 1, ',', ' ').' п.п.' : $f(abs($diff)) }}</strong>
                                        @if ($rel !== null)
                                            <span style="font-size:.75rem">({{ $rel > 0 ? '+' : ($rel < 0 ? '−' : '') }}{{ number_format(abs($rel), 0, ',', ' ') }}%)</span>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if (($compareData['phoneStateA']['ok'] ?? true) === false || ($compareData['phoneStateB']['ok'] ?? true) === false)
                <div class="mc-foot mc-mid">
                    Binotel: {{ $compareData['phoneStateA']['error'] ?? ($compareData['phoneStateB']['error'] ?? '') }}
                </div>
            @else
                <div class="mc-foot">Телефонні показники («…» — ще вантажаться) беруться з Binotel за кожен період окремо; для періоду довше 62 днів Binotel недоступний.</div>
            @endif
        </x-filament::section>

        <div class="mc-cmp-grid" style="margin-top:1.5rem">
            <div>
                <div class="mc-rec-sub" style="font-weight:700;margin-bottom:.4rem">Раніший період · {{ $fmtDates($compareData['period1']) }}</div>
                @include('filament.pages.manager-conversion-card', ['m' => $A, 'norm' => $norm, 'budgetPercent' => $budgetPercent])
            </div>
            <div>
                <div class="mc-rec-sub" style="font-weight:700;margin-bottom:.4rem">Пізніший період · {{ $fmtDates($compareData['period2']) }}</div>
                @include('filament.pages.manager-conversion-card', ['m' => $B, 'norm' => $norm, 'budgetPercent' => $budgetPercent])
            </div>
        </div>
