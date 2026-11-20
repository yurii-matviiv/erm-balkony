{{-- One manager card (also used twice in compare mode). Vars: $m (manager row), $norm, $budgetPercent, optional $caption. --}}
                @php
                    $rateClass = $m['rate'] >= 30 ? 'mc-good' : ($m['rate'] >= 15 ? 'mc-mid' : 'mc-bad');
                @endphp
                <x-filament::section>
                    <div class="mc-name" title="{{ $m['name'] }}">{{ $m['name'] }}</div>

                    <div class="mc-metrics">
                        <div>
                            <div class="mc-label">Заявок</div>
                            <div class="mc-value">{{ number_format($m['leads'], 0, '.', ' ') }}</div>
                        </div>
                        <div>
                            <div class="mc-label">В замовлення</div>
                            <div class="mc-value">{{ number_format($m['converted'], 0, '.', ' ') }}</div>
                        </div>
                        <div>
                            <div class="mc-label">Конверсія</div>
                            <div class="mc-value {{ $rateClass }}">{{ number_format($m['rate'], 1, '.', ' ') }}%</div>
                        </div>
                        <div>
                            <div class="mc-label">Оборот</div>
                            <div class="mc-value mc-good">{{ number_format($m['turnover'], 0, '.', ' ') }} ₴</div>
                        </div>
                    </div>

                    <div class="mc-result">
                        <div>
                            <div class="mc-label">Витрати на рекламу для менеджера</div>
                            <div class="mc-sub">{{ number_format($m['ad_cost'], 0, '.', ' ') }} / {{ number_format($m['ad_budget'], 0, '.', ' ') }} ₴</div>
                            <div style="font-size:.8rem;margin-top:.15rem">
                                <strong class="{{ ($m['drr'] ?? 0) > $budgetPercent ? 'mc-bad' : 'mc-good' }}">{{ $m['drr'] === null ? '—' : number_format($m['drr'], 1, ',', ' ').'%' }}</strong>
                                <span style="opacity:.6">від обороту</span>
                            </div>
                        </div>
                        <div style="text-align:right">
                            <div class="mc-label">Результат</div>
                            <div class="mc-value {{ $m['result'] >= 0 ? 'mc-good' : 'mc-bad' }}">{{ $m['result'] >= 0 ? '+' : '−' }}{{ number_format(abs($m['result']), 0, '.', ' ') }} ₴</div>
                        </div>
                    </div>

                    @php
                        $loadLabel = ['low' => 'Низьке · є запас', 'ok' => 'Нормальне', 'full' => 'На межі', 'over' => 'Перевантаження', 'none' => '—'][$m['load_level']];
                        $loadClass = ['low' => 'mc-low', 'ok' => 'mc-good', 'full' => 'mc-mid', 'over' => 'mc-bad', 'none' => ''][$m['load_level']];
                    @endphp
                    <div class="mc-load">
                        <div class="mc-label">Навантаження (заявок / робочий день)</div>
                        <div class="mc-load-head">
                            <span><strong>{{ $m['per_day'] === null ? '—' : number_format($m['per_day'], 1, ',', ' ') }}</strong> з {{ $norm }} · пік дня: {{ $m['peak'] }}</span>
                            <strong class="{{ $loadClass }}">{{ $loadLabel }}{{ $m['load'] !== null ? ' · '.$m['load'].'%' : '' }}</strong>
                        </div>
                        <div class="mc-bar mc-bar-{{ $m['load_level'] }}"><div style="width: {{ min(100, $m['load'] ?? 0) }}%"></div></div>
                    </div>

                    <div class="mc-bar"><div style="width: {{ min(100, $m['rate']) }}%"></div></div>
                    <div class="mc-foot">Замовлень за період: {{ number_format($m['orders'], 0, '.', ' ') }}</div>
                </x-filament::section>
