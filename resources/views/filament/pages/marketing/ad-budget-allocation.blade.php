<x-filament-panels::page>
    {{-- Own CSS (not Tailwind utilities): new utility classes would need `npm run build`.
         Cards instead of a wide table, so everything fits at 100% zoom. --}}
    <style>
        .ab-grid { display: grid; grid-template-columns: 1fr; gap: 1rem; }
        @media (min-width: 700px)  { .ab-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (min-width: 1250px) { .ab-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
        .ab-name { font-size: .9rem; font-weight: 600; line-height: 1.25; overflow-wrap: anywhere; }
        .ab-sub { margin-top: .2rem; font-size: .72rem; opacity: .6; }
        .ab-badges { display: flex; flex-wrap: wrap; gap: .3rem; margin-top: .45rem; }
        .ab-badge { display: inline-block; padding: 0 .45rem; border-radius: 9999px; font-size: .65rem; background: rgba(127,127,127,.2); }
        .ab-metrics { display: grid; grid-template-columns: 1fr 1fr; gap: .8rem .8rem; margin-top: .9rem; }
        .ab-label { margin-bottom: .1rem; font-size: .62rem; text-transform: uppercase; letter-spacing: .04em; opacity: .6; }
        .ab-value { font-size: 1.05rem; font-weight: 700; line-height: 1.25; }
        .ab-note { font-size: .65rem; opacity: .55; line-height: 1.2; }
        .ab-result { display: flex; justify-content: space-between; align-items: flex-end; gap: .5rem; margin-top: 1rem; padding-top: .8rem; border-top: 1px solid rgba(127,127,127,.25); }
        .ab-big { font-size: 1.2rem; font-weight: 700; white-space: nowrap; }
        .ab-up { color: var(--success-600); font-weight: 700; font-size: .85rem; }
        .ab-down { color: var(--danger-600); font-weight: 700; font-size: .85rem; }
        .ab-hold { opacity: .7; font-size: .85rem; }
        .ab-bar { position: relative; height: 6px; margin-top: .7rem; border-radius: 9999px; background: rgba(127,127,127,.25); }
        .ab-bar-now { position: absolute; top: 0; left: 0; height: 100%; border-radius: 9999px; background: rgba(127,127,127,.7); }
        .ab-bar-rec { position: absolute; top: -3px; width: 3px; height: 12px; border-radius: 2px; background: var(--primary-600); }
        .ab-row { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; padding: .45rem 0; border-bottom: 1px solid rgba(127,127,127,.15); font-size: .8rem; }
        .ab-row > .ab-grow { flex: 1 1 14rem; overflow-wrap: anywhere; }
        .ab-muted { opacity: .55; font-size: .75rem; }
    </style>

    <x-filament::section compact>
        <div style="font-size:.85rem;line-height:1.45">
            <strong>Що це:</strong> порівняння рекламних кампаній за <strong>оборотом</strong>, який вони приносять (замовлення клієнтів із їхніх заявок), і <strong>витратами</strong> на них (Google Ads).
            Чим більше обороту на гривню витрат, тим вигідніша кампанія. Рекомендована частка підказує, куди переносити бюджет. Усе в відсотках від загальних витрат.
        </div>
    </x-filament::section>

    <x-filament::section compact>
        <div style="display:flex;flex-wrap:wrap;gap:.5rem 2rem;align-items:center;justify-content:space-between;font-size:.82rem">
            <div>
                Період: <strong>{{ \Carbon\Carbon::parse($data['from'])->format('d.m.Y') }} — {{ \Carbon\Carbon::parse($data['to'])->format('d.m.Y') }}</strong>
                <span class="ab-muted">(останні 14 днів не враховуються: замовлення з'являються із запізненням)</span><br>
                Дані Google оновлено: <strong>{{ $data['synced_at'] ? \Carbon\Carbon::parse($data['synced_at'])->format('d.m.Y H:i') : 'ще ні' }}</strong>
                <span class="ab-muted">(оновлюється автоматично при відкритті сторінки)</span>
            </div>
        </div>
    </x-filament::section>

    @if (count($data['rows']) === 0)
        <x-filament::section>Даних поки немає: не вдалося отримати їх з Google Ads.</x-filament::section>
    @else
        <div class="ab-grid">
            @foreach ($data['rows'] as $r)
                <x-filament::section compact>
                    <div class="ab-name" title="{{ $r['name'] }}">{{ $r['name'] }}</div>
                    <div class="ab-sub">
                        {{ $r['status'] === 'ENABLED' ? 'увімкнена' : ($r['status'] === 'PAUSED' ? 'на паузі' : $r['status']) }}
                        @if ($r['last_spend']) · остання витрата {{ \Carbon\Carbon::parse($r['last_spend'])->format('d.m') }} @endif
                    </div>
                    @if ($r['low_confidence'])
                        <div class="ab-badges"><span class="ab-badge">мало даних (замовлень: {{ $r['orders'] }})</span></div>
                    @endif

                    <div class="ab-metrics">
                        <div>
                            <div class="ab-label">Частка витрат</div>
                            <div class="ab-value">{{ number_format($r['spend_share'], 0) }}%</div>
                        </div>
                        <div>
                            <div class="ab-label">Частка обороту</div>
                            <div class="ab-value">{{ number_format($r['revenue_share'], 0) }}%</div>
                        </div>
                        <div>
                            <div class="ab-label">Віддача</div>
                            <div class="ab-value">{{ number_format($r['roas'], 1) }}×</div>
                            <div class="ab-note">середня {{ number_format($data['avg_roas'], 1) }}×</div>
                        </div>
                        <div>
                            <div class="ab-label">Запас росту</div>
                            @if ($r['headroom_lost'] === null)
                                <div class="ab-value">—</div>
                                <div class="ab-note">невідомо</div>
                            @else
                                <div class="ab-value">{{ number_format($r['headroom_lost'] * 100, 0) }}%</div>
                                <div class="ab-note">показів втрачено через бюджет</div>
                            @endif
                        </div>
                    </div>

                    <div class="ab-result">
                        <div>
                            <div class="ab-label">Рекомендована частка</div>
                            <div class="ab-big">{{ number_format($r['spend_share'], 0) }}% → {{ number_format($r['rec_share'], 0) }}%</div>
                        </div>
                        <div style="text-align:right">
                            @if ($r['action'] === 'up')
                                <span class="ab-up">Збільшити<br>+{{ number_format($r['delta'], 0) }} п.п.</span>
                            @elseif ($r['action'] === 'down')
                                <span class="ab-down">Зменшити<br>{{ number_format($r['delta'], 0) }} п.п.</span>
                            @else
                                <span class="ab-hold">Тримати</span>
                            @endif
                        </div>
                    </div>
                    <div class="ab-bar" title="сіра смуга — зараз, синя риска — рекомендація">
                        <div class="ab-bar-now" style="width: {{ min(100, $r['spend_share']) }}%"></div>
                        <div class="ab-bar-rec" style="left: calc({{ min(100, $r['rec_share']) }}% - 1px)"></div>
                    </div>
                    @if ($r['low_confidence'])
                        <div class="ab-note" style="margin-top:.4rem">Мало замовлень: частку не змінюємо.</div>
                    @endif
                </x-filament::section>
            @endforeach
        </div>

        <p class="ab-muted">
            «Віддача» = оборот ÷ витрати. «Запас росту» — скільки показів кампанія втрачає через обмежений бюджет (лише пошук).
            Це підказка, не наказ: рішення за агентством. Результат змін варто оцінювати через 4–6 тижнів.
        </p>
    @endif

    @if ($data['unmapped']->sum('leads') > 0)
        <p class="ab-muted">Не враховано заявок без відомої кампанії (мітка «not set» або нова назва): {{ $data['unmapped']->sum('leads') }}.</p>
    @endif
</x-filament-panels::page>
