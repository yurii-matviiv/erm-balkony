<?php

namespace App\Filament\Pages\Marketing;

use App\Filament\Concerns\HasInstructionModal;
use App\Filament\Concerns\PersistsPageState;
use App\Filament\Concerns\RequiresViewPermission;
use App\Services\Seo\SeoExclusionService;
use App\Services\Seo\SeoReportService;
use App\Services\Seo\SeoSnapshotService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * «SEO: позиції Google»: знімки з Search Console API (seo:positions-snapshot, щопонеділка).
 * Лише стандартні елементи Filament: вкладки, таблиця (records() — дані з масиву), фільтр, кнопки в шапці.
 * Видима тільки super_admin, поки роль не ввімкнено в «Бокова панель».
 */
class SeoPositions extends Page implements HasTable
{
    use HasInstructionModal;
    use InteractsWithTable;
    use PersistsPageState;
    use RequiresViewPermission;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-magnifying-glass-circle';

    protected static ?string $navigationLabel = 'SEO: позиції Google';

    protected static ?string $title = 'SEO: позиції Google';

    protected static ?string $slug = 'seo-positions';

    protected static string|\UnitEnum|null $navigationGroup = 'Маркетинг';

    protected static ?int $navigationSort = 30;

    protected Width|string|null $maxContentWidth = Width::Full;

    protected string $view = 'filament.pages.marketing.seo-positions';

    public string $activeTab = 'changes';

    /** Вибраний знімок (дата) — завжди видимий рядок над таблицею. */
    public ?string $snapshot = null;

    /** Ховати виключені ключові слова (за замовчуванням увімкнено). */
    public bool $hideExcluded = true;

    public function mount(): void
    {
        $this->snapshot = $this->snapshotDates()[0] ?? null;
        $this->restorePageState();
    }

    /** Що пам'ятати між візитами: вкладка, знімок, галочка приховування. */
    protected function persistedState(): array
    {
        return [
            'activeTab' => array_keys($this->getTabs()),
            'snapshot' => $this->snapshotDates(),
            'hideExcluded' => [true, false],
        ];
    }

    /** @return array<string,string> дата => підпис */
    public function snapshotOptions(): array
    {
        return collect($this->snapshotDates())->mapWithKeys(fn ($d) => [$d => Carbon::parse($d)->format('d.m.Y')])->all();
    }

    private const SCOPE_TABS = ['new_page', 'new_page_query', 'top_page', 'top_query', 'prospects'];

    /** @return array<string,string> */
    public function getTabs(): array
    {
        return [
            'changes' => 'Що змінилось',
            'new_page' => 'Нові сторінки',
            'new_page_query' => 'Запити нових сторінок',
            'top_page' => 'Топ-30 сторінок',
            'top_query' => 'Топ-30 запитів',
            'prospects' => 'Перспективні слова',
            'excluded' => 'Виключені слова',
            'index' => 'Індексація',
        ];
    }

    public function excludedCount(): int
    {
        return app(SeoExclusionService::class)->count();
    }

    public function setTab(string $tab): void
    {
        if (! array_key_exists($tab, $this->getTabs())) {
            return;
        }

        $this->activeTab = $tab;
        $this->resetPage();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addExcluded')
                ->label('Додати виключені слова')
                ->icon('heroicon-o-plus')
                ->visible(fn (): bool => $this->activeTab === 'excluded')
                ->modalHeading('Виключити ключові слова')
                ->modalDescription('Вони лише ховаються у списках (точний збіг фрази); у знімках дані лишаються.')
                ->modalSubmitActionLabel('Додати')
                ->schema([Textarea::make('words')->label('Слова — по одному в рядок')->rows(6)->required()])
                ->action(function (array $data): void {
                    $n = app(SeoExclusionService::class)->add(preg_split('/\R/u', (string) $data['words']), 'manual');
                    Notification::make()->title("Додано: {$n}")->success()->send();
                }),

            Action::make('analyzeWord')
                ->label('Аналіз слова')
                ->icon('heroicon-o-key')
                ->color('gray')
                ->modalHeading('Аналіз ключового слова')
                ->modalDescription('Будь-яке слово чи фраза — дані беруться з Google Search Console.')
                ->modalSubmitActionLabel('Відкрити')
                ->schema([TextInput::make('keyword')->label('Слово або фраза (точно, як у Google)')->required()])
                ->action(fn (array $data) => $this->redirect(SeoKeyword::getUrl(['q' => trim($data['keyword'])]))),

            $this->instructionAction('seoInstruction', 'SEO: позиції Google — як це працює', 'filament.pages.marketing.seo-positions-instruction', [
                'positionChange' => config('seo.position_change'),
                'impressionsPct' => config('seo.impressions_change_pct'),
                'minImpressions' => config('seo.min_impressions'),
                'windowDays' => config('seo.window_days'),
                'lagDays' => config('seo.lag_days'),
                'topLimit' => config('seo.top_limit'),
            ]),

            Action::make('indexNow')
                ->label('Оновити індексацію')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Перевірити індексацію нових сторінок?')
                ->modalDescription('16 запитів до Google (URL Inspection), може тривати до хвилини.')
                ->action(function (): void {
                    try {
                        @set_time_limit(180);
                        $res = app(SeoSnapshotService::class)->storeIndexStatus(0, 100);
                        Notification::make()->title('Індексацію оновлено: '.count($res).' сторінок')->success()->send();
                    } catch (\Throwable $e) {
                        Notification::make()->title('Не вдалося оновити індексацію: '.$e->getMessage())->danger()->send();
                    }
                }),

            Action::make('snapshotNow')
                ->label('Зробити знімок зараз')
                ->icon('heroicon-o-camera')
                ->requiresConfirmation()
                ->modalHeading('Зробити знімок позицій зараз?')
                ->modalDescription('Дані беруться з Google Search Console (лише читання) і записуються в базу. Повторний знімок того ж дня оновлює вже записаний.')
                ->action(function (): void {
                    try {
                        $svc = app(SeoSnapshotService::class);
                        $n = $svc->store($svc->collect());
                        Notification::make()->title("Знімок записано: {$n} рядків")->success()->send();
                    } catch (\Throwable $e) {
                        Notification::make()->title('Не вдалося зробити знімок: '.$e->getMessage())->danger()->send();
                    }
                }),
        ];
    }

    /** Таблиця будується один раз на початку запиту, тому все, що залежить від вкладки, — ліниве (замикання). */
    public function table(Table $table): Table
    {
        return $table
            ->records(fn (?string $search, ?string $sortColumn, ?string $sortDirection): Collection => $this->records($this->activeTab, $search, $sortColumn, $sortDirection))
            ->striped()
            ->paginated(false)
            ->recordActions([
                Action::make('restore')->label('Повернути')->icon('heroicon-o-arrow-uturn-left')->color('gray')
                    ->visible(fn (): bool => $this->activeTab === 'excluded')
                    ->requiresConfirmation()
                    ->modalHeading('Повернути слово у списки?')
                    ->action(function (array $record): void {
                        app(SeoExclusionService::class)->remove((int) $record['id']);
                    }),
            ])
            ->columns($this->columns())
            ->emptyStateHeading('Даних ще немає')
            ->emptyStateDescription(fn (): string => match ($this->activeTab) {
                'index' => 'Натисніть «Оновити індексацію».',
                'changes' => 'Змін немає або порівняння з’явиться після другого знімка.',
                default => 'Натисніть «Зробити знімок зараз».',
            });
    }

    /** @return array<int,mixed> */
    private function columns(): array
    {
        $pos = fn ($state): string => $state === null ? '—' : number_format((float) $state, 1, ',', '');
        $scope = fn (): bool => in_array($this->activeTab, self::SCOPE_TABS, true);
        $on = fn (string ...$tabs): \Closure => fn (): bool => in_array($this->activeTab, $tabs, true);

        return [
            // Що змінилось
            TextColumn::make('type_label')->label('Тип')->badge()->visible($on('changes'))
                ->color(fn (array $record): string => match ($record['type'] ?? '') {
                    'appeared' => 'success', 'lost_query' => 'danger', default => 'warning',
                }),
            TextColumn::make('label')
                ->label(fn (): string => $this->activeTab === 'excluded' ? 'Слово' : (in_array($this->activeTab, ['top_query', 'new_page_query', 'prospects'], true) ? 'Запит' : 'Сторінка / запит'))
                ->wrap()->searchable()->visible($on('changes', 'new_page', 'new_page_query', 'top_page', 'top_query', 'prospects', 'excluded'))
                ->description(fn (array $record): ?string => $record['top_queries'] ?? null)
                // Запит → окрема сторінка аналізу слова.
                ->url(fn (array $record): ?string => isset($record['keyword']) ? SeoKeyword::getUrl(['q' => $record['keyword']]) : null),
            TextColumn::make('text')->label('Зміна')->wrap()->visible($on('changes')),

            TextColumn::make('group')->label('Група')->badge()->visible($on('prospects'))
                ->color(fn (?string $state): string => match ($state) {
                    'Закріплене' => 'info', 'Майже на вершині' => 'success', 'Межа 1-ї сторінки' => 'warning', default => 'gray',
                }),
            TextColumn::make('potential')->label('Потенціал, кліків/міс')->numeric(1)->sortable()->visible($on('prospects')),

            TextColumn::make('source_label')->label('Додано')->badge()->visible($on('excluded')),
            TextColumn::make('added_at')->label('Дата')->visible($on('excluded')),

            // Індексація
            TextColumn::make('page')->label('Сторінка')->wrap()->searchable()->visible($on('index')),
            TextColumn::make('coverage_state')->label('Стан індексації')->badge()->placeholder('—')->visible($on('index'))
                ->color(fn (?string $state): string => match (true) {
                    $state !== null && str_contains($state, 'indexed') && ! str_contains($state, 'not indexed') => 'success',
                    $state !== null && str_contains($state, 'unknown') => 'danger',
                    default => 'warning',
                }),
            TextColumn::make('last_crawl_time')->label('Останній скан')->placeholder('—')->visible($on('index')),
            TextColumn::make('google_canonical')->label('Канонічна (Google)')->placeholder('—')->wrap()->visible($on('index')),

            // Позиції
            TextColumn::make('impressions')->label('Покази')->numeric()->sortable()->visible($scope),
            TextColumn::make('clicks')->label('Кліки')->numeric()->sortable()->visible($scope),
            TextColumn::make('position')->label('Позиція')->formatStateUsing($pos)->sortable()->visible($scope),
            TextColumn::make('prev_position')->label('Було')->formatStateUsing($pos)->visible($scope),
            TextColumn::make('delta')->label('Зміна позиції')->sortable()->visible($scope)
                ->formatStateUsing(fn ($state): string => $state === null ? '—' : ($state < 0 ? '▲ ' : '▼ +').number_format(abs((float) $state), 1, ',', ''))
                ->color(fn ($state): ?string => $state === null || abs((float) $state) < 0.05 ? null : ($state < 0 ? 'success' : 'danger')),
        ];
    }

    /** @return list<string> дати знімків, найновіша першою */
    private function snapshotDates(): array
    {
        return DB::table('seo_position_snapshots')->distinct()->orderByDesc('snapshot_date')->pluck('snapshot_date')->all();
    }

    private function records(string $tab, ?string $search, ?string $sortColumn, ?string $sortDirection): Collection
    {
        if ($tab === 'changes') {
            $labels = ['appeared' => 'З’явилась у видачі', 'position' => 'Зміна позиції', 'impressions' => 'Зміна показів', 'lost_query' => 'Втрачений запит'];
            $rows = collect(app(SeoReportService::class)->changes($this->snapshot)['events'])
                ->map(fn (array $e): array => $e + ['type_label' => $labels[$e['type']] ?? $e['type']]);
        } elseif ($tab === 'excluded') {
            $rows = DB::table('seo_excluded_keywords')->whereNull('deleted_at')->orderByDesc('id')->get()
                ->map(fn ($r): array => [
                    'id' => $r->id, 'label' => $r->keyword, 'keyword' => $r->keyword,
                    'source_label' => $r->source === 'auto' ? 'Автоматично' : 'Вручну',
                    'added_at' => Carbon::parse($r->created_at)->format('d.m.Y'),
                ]);
        } elseif ($tab === 'index') {
            $chosen = $this->snapshot;
            $last = DB::table('seo_index_status')->when($chosen, fn ($q) => $q->where('snapshot_date', '<=', $chosen))->max('snapshot_date')
                ?? DB::table('seo_index_status')->max('snapshot_date');
            $rows = DB::table('seo_index_status')->where('snapshot_date', $last)->orderBy('page')->get()
                ->map(fn ($r): array => (array) $r);
        } else {
            $rows = $tab === 'prospects' ? $this->prospectRows($this->snapshot) : $this->scopeRows($tab, $this->snapshot);
        }

        if ($this->hideExcluded && $tab !== 'excluded') {
            $ex = app(SeoExclusionService::class)->hashes();
            $rows = $rows->reject(fn (array $r): bool => ! empty($r['keyword']) && isset($ex[sha1(SeoExclusionService::norm($r['keyword']))]));
        }

        if (filled($search)) {
            $rows = $rows->filter(fn (array $r): bool => mb_stripos((string) ($r['label'] ?? $r['page'] ?? ''), $search) !== false);
        }

        if ($sortColumn) {
            $rows = $rows->sortBy(fn (array $r) => $r[$sortColumn] ?? 0, SORT_REGULAR, $sortDirection === 'desc');
        }

        return $rows->values();
    }

    /** Перспективні слова: усі запити знімка з групою й потенціалом кліків. */
    private function prospectRows(?string $date): Collection
    {
        $dates = $this->snapshotDates();
        $date = in_array($date, $dates, true) ? $date : ($dates[0] ?? null);
        if (! $date) {
            return collect();
        }
        $prevDate = $dates[array_search($date, $dates, true) + 1] ?? null;
        $min = (int) config('seo.min_impressions');
        $minImp = (int) config('seo.prospect_min_impressions');
        $target = (int) config('seo.prospect_target_position');
        $bench = config('seo.ctr_benchmark');
        $ctrAt = fn (float $p): float => $bench[(int) max(1, min(10, round($p)))] ?? $bench['rest'];
        $pinned = array_map('mb_strtolower', (array) config('seo.pinned_queries'));

        $prev = $prevDate ? DB::table('seo_position_snapshots')->where('scope', 'query_all')->where('snapshot_date', $prevDate)->get()->keyBy('query_hash') : collect();

        return DB::table('seo_position_snapshots')->where('scope', 'query_all')->where('snapshot_date', $date)->get()
            ->map(function ($r) use ($prev, $min, $minImp, $target, $ctrAt, $bench, $pinned): ?array {
                $isPinned = in_array(mb_strtolower($r->query), $pinned, true);
                $pos = $r->position === null ? null : (float) $r->position;
                if (! $isPinned && ($pos === null || $r->impressions < $minImp || $pos > 20)) {
                    return null;
                }
                $ctr = (float) $r->ctr;
                if ($pos === null) {
                    $group = 'Закріплене';
                    $potential = 0.0;
                } elseif ($pos > $target) {
                    $group = $isPinned ? 'Закріплене' : ($pos <= 10 ? 'Майже на вершині' : 'Межа 1-ї сторінки');
                    $potential = max(0.0, $r->impressions * ($ctrAt($target) - ($pos > 10 ? $bench['rest'] : $ctrAt($pos))));
                } else {
                    // Вже в топ-3: резерв лише якщо реальний CTR нижчий за типовий для позиції.
                    $gap = $ctrAt($pos) - $ctr;
                    if (! $isPinned && $gap <= 0.02) {
                        return null;
                    }
                    $group = $isPinned ? 'Закріплене' : 'Низький CTR у топ-3';
                    $potential = max(0.0, $r->impressions * $gap);
                }
                $p = $prev[$r->query_hash] ?? null;
                $delta = ($p && $pos !== null && $p->position !== null && $r->impressions >= $min && $p->impressions >= $min)
                    ? round($pos - (float) $p->position, 1) : null;

                return [
                    'label' => $r->query,
                    'keyword' => $r->query,
                    'top_queries' => $r->page ? 'Сторінка: '.(parse_url($r->page, PHP_URL_PATH) ?: $r->page) : null,
                    'group' => $group,
                    'impressions' => (int) $r->impressions,
                    'clicks' => (int) $r->clicks,
                    'position' => $r->position,
                    'prev_position' => $p?->position,
                    'delta' => $delta,
                    'potential' => round($potential, 1),
                ];
            })->filter()->sortByDesc('potential')->values();
    }

    /** Рядки вибраного знімка + порівняння з попереднім. */
    private function scopeRows(string $scope, ?string $date): Collection
    {
        $dates = $this->snapshotDates();
        $date = in_array($date, $dates, true) ? $date : ($dates[0] ?? null);
        if (! $date) {
            return collect();
        }
        $prevDate = $dates[array_search($date, $dates, true) + 1] ?? null;
        $min = (int) config('seo.min_impressions');

        $key = fn ($r) => $r->page_hash.'|'.$r->query_hash;
        $now = DB::table('seo_position_snapshots')->where('scope', $scope)->where('snapshot_date', $date)->get();
        $prev = $prevDate ? DB::table('seo_position_snapshots')->where('scope', $scope)->where('snapshot_date', $prevDate)->get()->keyBy($key) : collect();

        // Головна сторінка запиту (найбільше показів) — для «звідки це слово».
        $mainPage = in_array($scope, ['top_query'], true)
            ? DB::table('seo_position_snapshots')->where('scope', 'query_page')->where('snapshot_date', $date)
                ->orderByDesc('impressions')->get()->groupBy('query')->map(fn ($g) => $g->first())
            : collect();

        $queries = $scope === 'new_page'
            ? DB::table('seo_position_snapshots')->where('scope', 'new_page_query')->where('snapshot_date', $date)
                ->orderByDesc('impressions')->get()->groupBy('page')
            : collect();

        return $now->map(function ($r) use ($scope, $prev, $key, $min, $queries): array {
            $p = $prev[$key($r)] ?? null;
            $delta = ($p && $r->position !== null && $p->position !== null && $r->impressions >= $min && $p->impressions >= $min)
                ? round((float) $r->position - (float) $p->position, 1) : null;

            return [
                'label' => in_array($scope, ['top_query', 'new_page_query'], true) ? $r->query : $r->page,
                'keyword' => in_array($scope, ['top_query', 'new_page_query'], true) ? $r->query : null,
                'impressions' => (int) $r->impressions,
                'clicks' => (int) $r->clicks,
                'position' => $r->position,
                'prev_position' => $p?->position,
                'delta' => $delta,
                'top_queries' => $scope === 'new_page_query'
                    ? 'Сторінка: '.(parse_url($r->page, PHP_URL_PATH) ?: $r->page)
                    : ($scope === 'top_query' && isset($mainPage[$r->query])
                        ? 'Головна сторінка: '.(parse_url($mainPage[$r->query]->page, PHP_URL_PATH) ?: $mainPage[$r->query]->page)
                        : (isset($queries[$r->page])
                    ? 'Топ-запити: '.$queries[$r->page]->take(5)->map(fn ($q) => $q->query.' ('.$q->position.')')->implode('; ')
                    : null)),
            ];
        })->sortBy([['clicks', 'desc'], ['impressions', 'desc']])->values();
    }
}
