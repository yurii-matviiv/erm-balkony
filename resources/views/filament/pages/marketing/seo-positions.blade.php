@php
    $th = 'padding:.5rem;border-bottom:1px solid #cfd8de;text-align:left;font-size:.85rem;white-space:nowrap;';
    $td = 'padding:.5rem;border-bottom:1px solid #e6ecef;font-size:.9rem;vertical-align:top;';
    $labels = ['appeared' => 'З’явилась у видачі', 'position' => 'Зміна позиції', 'impressions' => 'Зміна показів', 'lost_query' => 'Втрачений запит'];
@endphp
<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Позиції в Google (Search Console API)</x-slot>
        <x-slot name="afterHeader">{{ $this->seoInstructionAction }}</x-slot>

        <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1rem;">
            @foreach ($this->getTabs() as $key => $label)
                <x-filament::button size="sm" :color="$activeTab === $key ? 'primary' : 'gray'" wire:click="$set('activeTab','{{ $key }}')">{{ $label }}</x-filament::button>
            @endforeach
        </div>

        @if (empty($dates))
            <p>Знімків ще немає. Перший знімок: <code>php artisan seo:positions-snapshot</code>.</p>
        @elseif ($activeTab === 'changes')
            @if (! $changes['previous'])
                <p>Поки лише один знімок ({{ $changes['current'] }}). Порівняння з’явиться після другого (щопонеділка).</p>
            @elseif (! count($changes['events']))
                <p>Між {{ $changes['previous'] }} і {{ $changes['current'] }} суттєвих змін немає.</p>
            @else
                <p style="margin-bottom:.5rem;">{{ $changes['previous'] }} → {{ $changes['current'] }}</p>
                <table style="width:100%;border-collapse:collapse;">
                    <thead><tr><th style="{{ $th }}">Тип</th><th style="{{ $th }}">Сторінка / запит</th><th style="{{ $th }}">Зміна</th></tr></thead>
                    <tbody>
                    @foreach ($changes['events'] as $e)
                        <tr><td style="{{ $td }}">{{ $labels[$e['type']] }}</td><td style="{{ $td }}">{{ $e['label'] }}</td><td style="{{ $td }}">{{ $e['text'] }}</td></tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
        @elseif ($activeTab === 'index')
            <table style="width:100%;border-collapse:collapse;">
                <thead><tr><th style="{{ $th }}">Сторінка</th><th style="{{ $th }}">Стан індексації</th><th style="{{ $th }}">Останній скан</th><th style="{{ $th }}">Канонічна (Google)</th></tr></thead>
                <tbody>
                @forelse ($index as $i)
                    <tr><td style="{{ $td }}">{{ $i->page }}</td><td style="{{ $td }}">{{ $i->coverage_state ?? '—' }}</td><td style="{{ $td }}">{{ $i->last_crawl_time ?? '—' }}</td><td style="{{ $td }}">{{ $i->google_canonical ?? '—' }}</td></tr>
                @empty
                    <tr><td style="{{ $td }}" colspan="4">Даних індексації ще немає: <code>php artisan seo:positions-snapshot --only=index</code>.</td></tr>
                @endforelse
                </tbody>
            </table>
        @else
            <p style="margin-bottom:.5rem;font-size:.85rem;">У клітинці: позиція (середня), нижче — покази / кліки. «—» — даних немає. Позиція за менш ніж {{ config('seo.min_impressions') }} показів — орієнтовна.</p>
            <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;">
                <thead><tr>
                    <th style="{{ $th }}">{{ $activeTab === 'top_query' ? 'Запит' : 'Сторінка' }}</th>
                    @foreach ($dates as $d)<th style="{{ $th }}">{{ \Illuminate\Support\Carbon::parse($d)->format('d.m.Y') }}</th>@endforeach
                </tr></thead>
                <tbody>
                @foreach ($matrix as $label => $byDate)
                    <tr>
                        <td style="{{ $td }}">{{ $label }}
                            @if ($activeTab === 'new_page' && isset($queries[$label]))
                                <div style="font-size:.8rem;opacity:.75;">Топ-запити: {{ $queries[$label]->take(5)->map(fn ($q) => $q->query.' ('.$q->position.')')->implode('; ') }}</div>
                            @endif
                        </td>
                        @foreach ($dates as $d)
                            @php($c = $byDate[$d] ?? null)
                            <td style="{{ $td }}">
                                @if ($c && $c->impressions > 0)
                                    <strong>{{ number_format($c->position, 1, ',', '') }}</strong>
                                    <div style="font-size:.8rem;opacity:.75;">{{ $c->impressions }} / {{ $c->clicks }}</div>
                                @else — @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
