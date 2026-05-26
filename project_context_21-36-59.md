# Контекст проекту
**Дата збору:** 2026-05-26 21:36:59
---

## Файл: app/Filament/Pages/Dashboard/MarketingAgencyDashboard.php
```php
<?php

namespace App\Filament\Pages\Dashboard;

use App\Filament\Widgets\Marketing\LeadLeadsChartWidget;
use App\Filament\Widgets\Marketing\LeadOrdersChartWidget;
use App\Filament\Widgets\Marketing\LeadStatsWidget;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;

/**
 * ---------------------------------------------------------
 * STACK / PROJECT STANDARD
 * ---------------------------------------------------------
 * Laravel 13.11.2
 * Livewire 3.8.0
 * Filament 4.11.5
 * Filament Shield 4.2.0
 * Spatie Permission 7.4.1
 * ---------------------------------------------------------
 * ACCESS:
 * Використовувати тільки Shield permissions.
 * НЕ використовувати hasRole().
 * ---------------------------------------------------------
 * FILAMENT 4 FILTERS API:
 * - HasFiltersForm
 * - Filament\Schemas\Schema
 * - filtersForm(Schema $schema): Schema
 *
 * НЕ використовувати:
 * - Filament\Forms\Form
 * - getFiltersFormSchema()
 * ---------------------------------------------------------
 */

class MarketingAgencyDashboard extends Page
{
    use HasFiltersForm;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Marketing Agency Dashboard';

    protected static ?string $title = 'Marketing Agency Dashboard';

    protected static string|\UnitEnum|null $navigationGroup = 'Dashboard';

    protected string $view = 'filament.pages.dashboard.marketing-agency-dashboard';

    protected Width|string|null $maxContentWidth = Width::Full;

    public array $currentFilters = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('View:MarketingAgencyDashboard') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->can('View:MarketingAgencyDashboard') ?? false;
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([

                Select::make('preset')
                    ->label('Період')
                    ->default('this_year')
                    ->reactive()
                    ->live()
                    ->options([
                        'today' => 'Сьогодні',
                        'yesterday' => 'Вчора',
                        'this_month' => 'Поточний місяць',
                        'last_30_days' => 'Останні 30 днів',
                        'this_year' => 'Поточний рік',
                        'custom' => 'Свій варіант',
                    ])
                    ->afterStateUpdated(function ($state, callable $set) {
                        match ($state) {
                            'today' => $set('date_from', now()->startOfDay()) && $set('date_to', now()->endOfDay()),
                            'yesterday' => $set('date_from', now()->subDay()->startOfDay()) && $set('date_to', now()->subDay()->endOfDay()),
                            'this_month' => $set('date_from', now()->startOfMonth()) && $set('date_to', now()->endOfMonth()),
                            'last_30_days' => $set('date_from', now()->subDays(30)) && $set('date_to', now()),
                            'this_year' => $set('date_from', now()->startOfYear()) && $set('date_to', now()->endOfYear()),
                            default => null,
                        };
                        $this->updateWidgets();
                    }),

                DatePicker::make('date_from')
                    ->label('Дата від')
                    ->default(now()->startOfYear())
                    ->required(false)
                    ->afterStateUpdated(fn () => $this->updateWidgets()),

                DatePicker::make('date_to')
                    ->label('Дата до')
                    ->default(now())
                    ->required(false)
                    ->afterStateUpdated(fn () => $this->updateWidgets()),

            ]);
    }

    protected function updateWidgets(): void
    {
        $this->currentFilters = $this->filtersForm->getState();
        $this->dispatch('refresh-widgets', filters: $this->currentFilters);
    }

    /**
     * ---------------------------------------------------------
     * HEADER WIDGETS
     * ---------------------------------------------------------
     */
  public function getWidgets(): array
{
    $filters = $this->filtersForm->getState() ?: [];
    
    return [
        LeadStatsWidget::make(['pageFilters' => $filters]),
        LeadLeadsChartWidget::make(['pageFilters' => $filters]),
        LeadOrdersChartWidget::make(['pageFilters' => $filters]),
    ];
}

public function getWidgetData(): array
{
    return [
        'filters' => $this->filtersForm->getState() ?: request()->query('filters', [])
    ];
}
}```

## Файл: app/Filament/Widgets/Marketing/LeadLeadsChartWidget.php
```php
<?php

namespace App\Filament\Widgets\Marketing;

use App\Models\Lead;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Livewire\Attributes\On;

class LeadLeadsChartWidget extends ChartWidget
{

   use InteractsWithPageFilters;
    protected ?string $heading = 'Leads trend';
    protected ?string $pollingInterval = null;

  /**
 * ---------------------------------------------------------
 * STACK / PROJECT STANDARD
 * ---------------------------------------------------------
 * Laravel 13.11.2
 * Livewire 3.8.0
 * Filament 4.11.5
 * Filament Shield 4.2.0
 * Spatie Permission 7.4.1
 * ---------------------------------------------------------
 * ACCESS:
 * Використовувати тільки Shield permissions.
 * НЕ використовувати hasRole().
 * ---------------------------------------------------------
 * FILAMENT 4 FILTERS API:
 * - HasFiltersForm
 * - Filament\Schemas\Schema
 * - filtersForm(Schema $schema): Schema
 *
 * НЕ використовувати:
 * - Filament\Forms\Form
 * - getFiltersFormSchema()
 * ---------------------------------------------------------
 */


    public static function canView(): bool
    {
        return auth()->user()?->can('View:MarketingAgencyDashboard') ?? false;
    }

    /**
     * ---------------------------------------------------------
     * CHART DATA
     * ---------------------------------------------------------
     */
       protected function getData(): array
    {
        $query = Lead::query()

            ->selectRaw('DATE(created_at) as date')
            ->selectRaw('COUNT(*) as total')

            ->where('status', 'accepted');

        /**
         * ---------------------------------------------------------
         * FILTERS
         * ---------------------------------------------------------
         */
        $filters = $this->pageFilters ?? $this->filters ?? [];
        $preset = $filters['preset'] ?? 'this_year';

        if ($preset === 'today') {

            $query->whereDate(
                'created_at',
                today()
            );
        }

        elseif ($preset === 'yesterday') {

            $query->whereDate(
                'created_at',
                today()->subDay()
            );
        }

        elseif ($preset === 'this_month') {

            $query
                ->whereDate(
                    'created_at',
                    '>=',
                    now()->startOfMonth()
                )
                ->whereDate(
                    'created_at',
                    '<=',
                    now()
                );
        }

        elseif ($preset === 'last_30_days') {

            $query
                ->whereDate(
                    'created_at',
                    '>=',
                    now()->subDays(30)
                )
                ->whereDate(
                    'created_at',
                    '<=',
                    now()
                );
        }

        elseif ($preset === 'custom') {

            if (!empty($filters['date_from'])) {           // ← виправлено

                $query->whereDate(
                    'created_at',
                    '>=',
                    Carbon::parse($filters['date_from'])
                );
            }

            if (!empty($filters['date_to'])) {             // ← виправлено

                $query->whereDate(
                    'created_at',
                    '<=',
                    Carbon::parse($filters['date_to'])
                );
            }
        }

        else {

            $query
                ->whereDate(
                    'created_at',
                    '>=',
                    now()->startOfYear()
                )
                ->whereDate(
                    'created_at',
                    '<=',
                    now()
                );
        }

        /**
         * ---------------------------------------------------------
         * GROUP + SORT
         * ---------------------------------------------------------
         */
        $data = $query

            ->groupByRaw('DATE(created_at)')

            ->orderByRaw('DATE(created_at) ASC')

            ->get();

        return [

            'datasets' => [
                [
                    'label' => 'Orders',

                    'data' => $data
                        ->pluck('total')
                        ->toArray(),
                ],
            ],

            'labels' => $data
                ->pluck('date')
                ->toArray(),
        ];
    }

    /**
     * ---------------------------------------------------------
     * CHART TYPE
     * ---------------------------------------------------------
     */
    protected function getType(): string
    {
        return 'line';
    }

    /**
     * ---------------------------------------------------------
     * LISTENER FOR FILTERS UPDATES
     * ---------------------------------------------------------
     */
    #[On('filters-updated')]
    public function handleFiltersUpdate(array $filters): void
    {
        $this->filters = $filters;
    }

    public function mount(array $filters = []): void
{
    $this->filters = $filters;
}

}```

## Файл: app/Filament/Widgets/Marketing/LeadOrdersChartWidget.php
```php
<?php

namespace App\Filament\Widgets\Marketing;

use App\Models\Lead;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Livewire\Attributes\On;

class LeadOrdersChartWidget extends ChartWidget
{
    use InteractsWithPageFilters;

/**
 * ---------------------------------------------------------
 * STACK / PROJECT STANDARD
 * ---------------------------------------------------------
 * Laravel 13.11.2
 * Livewire 3.8.0
 * Filament 4.11.5
 * Filament Shield 4.2.0
 * Spatie Permission 7.4.1
 * ---------------------------------------------------------
 * ACCESS:
 * Використовувати тільки Shield permissions.
 * НЕ використовувати hasRole().
 * ---------------------------------------------------------
 * FILAMENT 4 FILTERS API:
 * - HasFiltersForm
 * - Filament\Schemas\Schema
 * - filtersForm(Schema $schema): Schema
 *
 * НЕ використовувати:
 * - Filament\Forms\Form
 * - getFiltersFormSchema()
 * ---------------------------------------------------------
 */

    protected ?string $heading = 'Orders trend';
    protected ?string $pollingInterval = null;

    /**
     * ---------------------------------------------------------
     * ACCESS
     * ---------------------------------------------------------
     */
    public static function canView(): bool
    {
        return auth()->user()?->can('View:MarketingAgencyDashboard') ?? false;
    }

    /**
     * ---------------------------------------------------------
     * CHART DATA
     * ---------------------------------------------------------
     * Dashboard filters API:
     * $this->filters
     * ---------------------------------------------------------
     */
    protected function getData(): array
    {
        $query = Lead::query()

            ->selectRaw('DATE(created_at) as date')
            ->selectRaw('COUNT(*) as total')

            ->where('status', 'accepted');

        /**
         * ---------------------------------------------------------
         * FILTERS
         * ---------------------------------------------------------
         */
$filters = $this->pageFilters ?? $this->filters ?? [];
$preset = $filters['preset'] ?? 'this_year';

        if ($preset === 'today') {

            $query->whereDate(
                'created_at',
                today()
            );
        }

        elseif ($preset === 'yesterday') {

            $query->whereDate(
                'created_at',
                today()->subDay()
            );
        }

        elseif ($preset === 'this_month') {

            $query
                ->whereDate(
                    'created_at',
                    '>=',
                    now()->startOfMonth()
                )
                ->whereDate(
                    'created_at',
                    '<=',
                    now()
                );
        }

        elseif ($preset === 'last_30_days') {

            $query
                ->whereDate(
                    'created_at',
                    '>=',
                    now()->subDays(30)
                )
                ->whereDate(
                    'created_at',
                    '<=',
                    now()
                );
        }

        elseif ($preset === 'custom') {

            if (!empty($this->filters['date_from'])) {

                $query->whereDate(
                    'created_at',
                    '>=',
                    Carbon::parse($this->filters['date_from'])
                );
            }

            if (!empty($this->filters['date_to'])) {

                $query->whereDate(
                    'created_at',
                    '<=',
                    Carbon::parse($this->filters['date_to'])
                );
            }
        }

        else {

            $query
                ->whereDate(
                    'created_at',
                    '>=',
                    now()->startOfYear()
                )
                ->whereDate(
                    'created_at',
                    '<=',
                    now()
                );
        }

        /**
         * ---------------------------------------------------------
         * GROUP + SORT
         * ---------------------------------------------------------
         */
        $data = $query

            ->groupByRaw('DATE(created_at)')

            ->orderByRaw('DATE(created_at) ASC')

            ->get();

        return [

            'datasets' => [
                [
                    'label' => 'Orders',

                    'data' => $data
                        ->pluck('total')
                        ->toArray(),
                ],
            ],

            'labels' => $data
                ->pluck('date')
                ->toArray(),
        ];
    }

    /**
     * ---------------------------------------------------------
     * CHART TYPE
     * ---------------------------------------------------------
     */
    protected function getType(): string
    {
        return 'line';
    }

    /**
     * ---------------------------------------------------------
     * LISTENER FOR FILTERS UPDATES
     * ---------------------------------------------------------
     */
    #[On('filters-updated')]
    public function handleFiltersUpdate(array $filters): void
    {
        $this->filters = $filters;
    }

    public function mount(array $filters = []): void
{
    $this->filters = $filters;
}
}```

## Файл: app/Filament/Widgets/Marketing/LeadStatsWidget.php
```php
<?php

namespace App\Filament\Widgets\Marketing;

use App\Models\Lead;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Carbon\Carbon;
use App\Services\Leads\LeadQueryService;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class LeadStatsWidget extends StatsOverviewWidget
{

 use InteractsWithPageFilters;
protected ?string $pollingInterval = null;


// ДОДАТИ ЦЕЙ РЯДОК
public array $filters = [];


 /**
 * ---------------------------------------------------------
 * STACK / PROJECT STANDARD
 * ---------------------------------------------------------
 * Laravel 13.11.2
 * Livewire 3.8.0
 * Filament 4.11.5
 * Filament Shield 4.2.0
 * Spatie Permission 7.4.1
 * ---------------------------------------------------------
 * ACCESS:
 * Використовувати тільки Shield permissions.
 * НЕ використовувати hasRole().
 * ---------------------------------------------------------
 * FILAMENT 4 FILTERS API:
 * - HasFiltersForm
 * - Filament\Schemas\Schema
 * - filtersForm(Schema $schema): Schema
 *
 * НЕ використовувати:
 * - Filament\Forms\Form
 * - getFiltersFormSchema()
 * ---------------------------------------------------------
 */
   
    public static function canView(): bool
    {
        return auth()->user()?->can('View:MarketingAgencyDashboard') ?? false;
    }

    /**
     * ---------------------------------------------------------
     * STATS
     * ---------------------------------------------------------
     * ЛОГІКА:
     *
     * Цільові:
     * processing
     * zamir
     * vizyt_ofis
     * accepted
     * measuring
     *
     * Не цільові:
     * not_targeted
     * another_city
     * reklamatsiya_amtech
     * reklamatsiya
     *
     * Невідомо:
     * new
     * canceled
     * propushcheno
     * всі інші статуси
     * ---------------------------------------------------------
     */
      protected function getStats(): array
{

\Log::info('=== FILTERS DEBUG ===', [
    'filters' => $this->filters ?? null,
    'pageFilters' => $this->pageFilters ?? null,
    'all_request' => request()->all(),
    'query_filters' => request()->query('filters'),
]);


    // Беремо фільтри БЕЗПОСЕРЕДНЬО З URL
    $filters = request()->query('filters', []);
    
    $query = \App\Models\Lead::query();

    $preset = $filters['preset'] ?? 'this_year';

    if ($preset === 'today') {

        $query->whereDate(
            'created_at',
            today()
        );
    }

    elseif ($preset === 'yesterday') {

        $query->whereDate(
            'created_at',
            today()->subDay()
        );
    }

    elseif ($preset === 'this_month') {

        $query
            ->whereDate(
                'created_at',
                '>=',
                now()->startOfMonth()
            )
            ->whereDate(
                'created_at',
                '<=',
                now()
            );
    }

    elseif ($preset === 'last_30_days') {

        $query
            ->whereDate(
                'created_at',
                '>=',
                now()->subDays(30)
            )
            ->whereDate(
                'created_at',
                '<=',
                now()
            );
    }

    elseif ($preset === 'custom') {

        if (!empty($filters['date_from'])) {

            $query->whereDate(
                'created_at',
                '>=',
                Carbon::parse($filters['date_from'])
            );
        }

        if (!empty($filters['date_to'])) {

            $query->whereDate(
                'created_at',
                '<=',
                Carbon::parse($filters['date_to'])
            );
        }
    }

    else {

        $query
            ->whereDate(
                'created_at',
                '>=',
                now()->startOfYear()
            )
            ->whereDate(
                'created_at',
                '<=',
                now()
            );
    }

    $rows = $query->get();

    return [

        Stat::make('Всього лідів', $rows->count()),

        Stat::make(
            'Цільові',
            $rows->whereIn('status', [
                'processing',
                'zamir',
                'vizyt_ofis',
                'accepted',
                'measuring',
            ])->count()
        ),

        Stat::make(
            'Не цільові',
            $rows->whereIn('status', [
                'not_targeted',
                'another_city',
                'reklamatsiya_amtech',
                'reklamatsiya',
            ])->count()
        ),

        Stat::make(
            'Невідомо',
            $rows->where('status', 'new')->count()
        ),

        Stat::make(
            'Продані',
            $rows->where('status', 'accepted')->count()
        ),

    ];
}

public function mount(array $filters = []): void
{
    $this->filters = $filters;
}

}```

## Файл: app/Filament/Pages/Exports/LeadExport.php
```php
<?php

namespace App\Filament\Pages\Exports;

use App\Services\Leads\LeadQueryService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Tables;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Notifications\Notification;


class LeadExport extends Page implements HasTable
{
    use InteractsWithTable;

    /**
     * ---------------------------------------------------------
     * FILAMENT + SHIELD + SPATIE PERMISSION
     * ---------------------------------------------------------
     * ACCESS ONLY THROUGH PERMISSIONS
     * DO NOT USE hasRole()
     * ---------------------------------------------------------
     */

    protected Width|string|null $maxContentWidth = Width::Full;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-arrow-down';

    protected static ?string $navigationLabel = 'Lead Export';

    protected static ?string $title = 'Lead Export';

    protected static string|\UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.exports.lead-export';

 /**
 * ---------------------------------------------------------
 * STACK / PROJECT STANDARD
 * ---------------------------------------------------------
 * Laravel 13.11.2
 * Livewire 3.8.0
 * Filament 4.11.5
 * Filament Shield 4.2.0
 * Spatie Permission 7.4.1
 * ---------------------------------------------------------
 * ACCESS:
 * Використовувати тільки Shield permissions.
 * НЕ використовувати hasRole().
 * ---------------------------------------------------------
 * FILAMENT 4 FILTERS API:
 * - HasFiltersForm
 * - Filament\Schemas\Schema
 * - filtersForm(Schema $schema): Schema
 *
 * НЕ використовувати:
 * - Filament\Forms\Form
 * - getFiltersFormSchema()
 * ---------------------------------------------------------
 */

    public static function canAccess(): bool
    {
        return auth()->user()?->can('View:LeadExport') ?? false;
    }

    /**
     * ---------------------------------------------------------
     * SIDEBAR NAVIGATION
     * ---------------------------------------------------------
     */
    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->can('View:LeadExport') ?? false;
    }

    /**
     * ---------------------------------------------------------
     * TABLE
     * ---------------------------------------------------------
     */
    public function table(Table $table): Table
    {
        return $table

            /**
             * ---------------------------------------------------------
             * QUERY
             * ---------------------------------------------------------
             */
            ->query(
                app(LeadQueryService::class)->getQuery()
            )

            /**
             * ---------------------------------------------------------
             * FILTERS
             * ---------------------------------------------------------
             */

            ->filters([

                Filter::make('date_range')

                    ->form([

                        Select::make('preset')
                            ->label('Період')
                            ->default('today')
                            ->live()

                            ->options([

                                'today' => 'Сьогодні',
                                'yesterday' => 'Вчора',
                                'this_month' => 'Поточний місяць',
                                'last_30_days' => 'Останні 30 днів',
                                'this_year' => 'Поточний рік',
                                'custom' => 'Свій варіант',

                            ])

                            ->afterStateUpdated(function ($state, callable $set) {

                                match ($state) {

                                    'today' => [
                                        $set('date_from', now()->startOfDay()),
                                        $set('date_to', now()->endOfDay()),
                                    ],

                                    'yesterday' => [
                                        $set('date_from', now()->subDay()->startOfDay()),
                                        $set('date_to', now()->subDay()->endOfDay()),
                                    ],

                                    'this_month' => [
                                        $set('date_from', now()->startOfMonth()),
                                        $set('date_to', now()->endOfMonth()),
                                    ],

                                    'last_30_days' => [
                                        $set('date_from', now()->subDays(30)),
                                        $set('date_to', now()),
                                    ],

                                    'this_year' => [
                                        $set('date_from', now()->startOfYear()),
                                        $set('date_to', now()->endOfYear()),
                                    ],

                                    default => null,
                                };
                            }),

                        DatePicker::make('date_from')
                            ->label('Дата від')
                            ->default(now()->startOfYear()),

                        DatePicker::make('date_to')
                            ->label('Дата до')
                            ->default(now()),

                    ])

                    ->query(function (Builder $query, array $data): Builder {

                        $preset = $data['preset'] ?? 'this_year';

                        return match ($preset) {

                            'today' => $query->whereDate(
                                'leads.created_at',
                                today()
                            ),

                            'yesterday' => $query->whereDate(
                                'leads.created_at',
                                today()->subDay()
                            ),

                            'this_month' => $query
                                ->whereDate('leads.created_at', '>=', now()->startOfMonth())
                                ->whereDate('leads.created_at', '<=', now()),

                            'last_30_days' => $query
                                ->whereDate('leads.created_at', '>=', now()->subDays(30))
                                ->whereDate('leads.created_at', '<=', now()),

                            'custom' => $query
                                ->when(
                                    $data['date_from'],
                                    fn (Builder $query, $date): Builder => $query->whereDate(
                                        'leads.created_at',
                                        '>=',
                                        Carbon::parse($date)
                                    )
                                )
                                ->when(
                                    $data['date_to'],
                                    fn (Builder $query, $date): Builder => $query->whereDate(
                                        'leads.created_at',
                                        '<=',
                                        Carbon::parse($date)
                                    )
                                ),

                            default => $query
                                ->whereDate('leads.created_at', '>=', now()->startOfYear())
                                ->whereDate('leads.created_at', '<=', now()),
                        };
                    }),

            ])

            /**
             * ---------------------------------------------------------
             * TABLE SETTINGS
             * ---------------------------------------------------------
             */
            ->deferLoading()

            ->defaultSort('leads.id', 'desc')

            ->paginated([25, 50, 100])

            ->striped()

            /**
             * ---------------------------------------------------------
             * COLUMNS
             * ---------------------------------------------------------
             */
            ->columns([

                /**
                 * ---------------------------------------------------------
                 * №
                 * ---------------------------------------------------------
                 */
                TextColumn::make('id')
                    ->label('№')
                    ->sortable(),

                /**
                 * ---------------------------------------------------------
                 * ЧАС
                 * ---------------------------------------------------------
                 */
                TextColumn::make('created_at')
                    ->label('час')
                    ->dateTime('d.m.Y H:i:s')
                    ->sortable(),

                /**
                 * ---------------------------------------------------------
                 * ДЖЕРЕЛО ТРАФІКУ
                 * ---------------------------------------------------------
                 */
                TextColumn::make('utm_source')
                    ->label('джерело трафіку'),

                /**
                 * ---------------------------------------------------------
                 * ПОДІЯ
                 * ---------------------------------------------------------
                 */
                TextColumn::make('source')
                    ->label('подія')

                    ->formatStateUsing(function (?string $state): string {

                        return match ($state) {

                            'call' => 'Дзвінок',
                            'office-visit' => 'Візит в офіс',
                            'binotel_chat' => 'Binotel chat',
                            'site' => 'Заявка з сайту',
                            'get_call_binotel' => 'Зворотній дзвінок',
                            'fb_lid' => 'Facebook lead',
                            'fb_chat' => 'Facebook chat',

                            default => '-',
                        };
                    })

                    ->badge(),

                /**
                 * ---------------------------------------------------------
                 * ЦІЛЬОВИЙ
                 * ---------------------------------------------------------
                 */
                BadgeColumn::make('lead_target_status')

                    ->label('цільовий')

                    ->state(function ($record) {

                        return $record->lead_status;
                    })

                    ->formatStateUsing(function (?string $state): string {

                        return match ($state) {

                            'processing',
                            'zamir',
                            'vizyt_ofis',
                            'accepted',
                            'measuring' => 'цільовий',

                            'not_targeted',
                            'another_city',
                            'reklamatsiya_amtech',
                            'reklamatsiya' => 'не цільовий',

                            'new' => 'невідомо',

                            default => 'інше',
                        };
                    })

                    ->color(function (?string $state): string {

                        return match ($state) {

                            'processing',
                            'zamir',
                            'vizyt_ofis',
                            'accepted',
                            'measuring' => 'success',

                            'not_targeted',
                            'another_city',
                            'reklamatsiya_amtech',
                            'reklamatsiya' => 'danger',

                            'new' => 'gray',

                            default => 'warning',
                        };
                    }),

                /**
                 * ---------------------------------------------------------
                 * СТАТУС
                 * ---------------------------------------------------------
                 */
                BadgeColumn::make('lead_status_group')

                    ->label('статус')

                    ->state(function ($record) {

                        return $record->lead_status;
                    })

                    ->formatStateUsing(function (?string $state): string {

                        return match ($state) {

                            'new' => 'новий',

                            'processing',
                            'zamir',
                            'vizyt_ofis',
                            'measuring' => 'в роботі',

                            'accepted' => 'продано',

                            'canceled',
                            'not_targeted',
                            'another_city',
                            'propushcheno',
                            'reklamatsiya',
                            'reklamatsiya_amtech' => 'скасовано',

                            default => 'невідомо',
                        };
                    })

                    ->color(function (?string $state): string {

                        return match ($state) {

                            'new' => 'gray',

                            'processing',
                            'zamir',
                            'vizyt_ofis',
                            'measuring' => 'warning',

                            'accepted' => 'success',

                            'canceled',
                            'not_targeted',
                            'another_city',
                            'propushcheno',
                            'reklamatsiya',
                            'reklamatsiya_amtech' => 'danger',

                            default => 'gray',
                        };
                    }),

                /**
                 * ---------------------------------------------------------
                 * ІМʼЯ
                 * ---------------------------------------------------------
                 */
                TextColumn::make('name')
                    ->label('імʼя')
                    ->searchable(),

                /**
                 * ---------------------------------------------------------
                 * EMAIL
                 * ---------------------------------------------------------
                 */
                TextColumn::make('email')
                    ->label('email')
                    ->toggleable(isToggledHiddenByDefault: true),

                /**
                 * ---------------------------------------------------------
                 * ТЕЛЕФОН
                 * ---------------------------------------------------------
                 */
                TextColumn::make('phone')
                    ->label('телефон'),

                /**
                 * ---------------------------------------------------------
                 * СУМА
                 * ---------------------------------------------------------
                 */
                TextColumn::make('total_price')
                    ->label('сума')
                    ->money('UAH', divideBy: 1),

                /**
                 * ---------------------------------------------------------
                 * КАМПАНІЯ
                 * ---------------------------------------------------------
                 */
                TextColumn::make('utm_campaign')
                    ->label('кампанія')
                    ->wrap()
                    ->limit(40),

                /**
                 * ---------------------------------------------------------
                 * GCLID
                 * ---------------------------------------------------------
                 */
                TextColumn::make('gclid')

                    ->label('gclid')

                    ->state(function ($record): string {

                        return filled($record->gclid)
                            ? 'отримано'
                            : 'не отримано';
                    })

                    ->badge()

                    ->color(function ($record): string {

                        return filled($record->gclid)
                            ? 'success'
                            : 'gray';
                    })

                    ->toggleable(isToggledHiddenByDefault: true),

            ]);
    }

    /**
     * ---------------------------------------------------------
     * HEADER ACTIONS
     * ---------------------------------------------------------
     */
   protected function getHeaderActions(): array
{
    return [

        Action::make('exportCsv')

            ->label('Export CSV')

            ->icon('heroicon-o-arrow-down-tray')

            ->color('warning')

            /**
             * ---------------------------------------------------------
             * FILAMENT LOADING STATE
             * ---------------------------------------------------------
             */
            ->url(function (): string {

    /**
     * ---------------------------------------------------------
     * GET ACTIVE DATE FILTER
     * ---------------------------------------------------------
     */
    $filters = $this->tableFilters['date_range'] ?? [];

    return route('lead-export.page', [

        'preset' => $filters['preset'] ?? null,

        'date_from' => $filters['date_from'] ?? null,

        'date_to' => $filters['date_to'] ?? null,

    ]);
})

->openUrlInNewTab(),

    ];
}
}```

## Файл: app/Http/Controllers/Exports/LeadExportController.php
```php
<?php

namespace App\Http\Controllers\Exports;

use App\Http\Controllers\Controller;
use App\Services\Leads\LeadQueryService;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Http\Request;

class LeadExportController extends Controller
{
    /**
 * ---------------------------------------------------------
 * STACK / PROJECT STANDARD
 * ---------------------------------------------------------
 * Laravel 13.11.2
 * Livewire 3.8.0
 * Filament 4.11.5
 * Filament Shield 4.2.0
 * Spatie Permission 7.4.1
 * ---------------------------------------------------------
 * ACCESS:
 * Використовувати тільки Shield permissions.
 * НЕ використовувати hasRole().
 * ---------------------------------------------------------
 * FILAMENT 4 FILTERS API:
 * - HasFiltersForm
 * - Filament\Schemas\Schema
 * - filtersForm(Schema $schema): Schema
 *
 * НЕ використовувати:
 * - Filament\Forms\Form
 * - getFiltersFormSchema()
 * ---------------------------------------------------------
 */

    /**
     * ---------------------------------------------------------
     * EXPORT PAGE
     * ---------------------------------------------------------
     * Shows loading screen before CSV download
     * ---------------------------------------------------------
     */
    public function page(Request $request)
    {
       return view(
    'exports.lead-export-loading',
    [

        'preset' => $request->preset,

        'date_from' => $request->date_from,

        'date_to' => $request->date_to,

    ]
);
    }

    /**
     * ---------------------------------------------------------
     * EXPORT CSV
     * ---------------------------------------------------------
     */
    public function export(Request $request): StreamedResponse
    {
        $fileName = 'lead-export-' . now()->format('Y-m-d-H-i-s') . '.csv';

        return response()->streamDownload(function () use ($request) {

            $handle = fopen('php://output', 'w');

            /**
             * ---------------------------------------------------------
             * UTF-8 BOM
             * ---------------------------------------------------------
             */
            fwrite($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            /**
             * ---------------------------------------------------------
             * HEADER
             * ---------------------------------------------------------
             */
            fputcsv($handle, [

                'ID',
                'Created At',
                'Lead Status',
                'Source',
                'UTM Source',
                'UTM Campaign',
                'Name',
                'Phone',
                'Email',
                'Order Status',
                'Total Price',

            ]);

            /**
             * ---------------------------------------------------------
             * DATA
             * ---------------------------------------------------------
             */
            app(LeadQueryService::class)

    ->getQuery()
 
    /**
 * ---------------------------------------------------------
 * DATE FILTERS
 * ---------------------------------------------------------
 */
->when(

    $request->preset === 'today',

    fn ($query) => $query->whereDate(
        'leads.created_at',
        today()
    )

)

->when(

    $request->preset === 'yesterday',

    fn ($query) => $query->whereDate(
        'leads.created_at',
        today()->subDay()
    )

)

->when(

    $request->preset === 'this_month',

    fn ($query) => $query
        ->whereDate(
            'leads.created_at',
            '>=',
            now()->startOfMonth()
        )
        ->whereDate(
            'leads.created_at',
            '<=',
            now()
        )

)

->when(

    $request->preset === 'last_30_days',

    fn ($query) => $query
        ->whereDate(
            'leads.created_at',
            '>=',
            now()->subDays(30)
        )
        ->whereDate(
            'leads.created_at',
            '<=',
            now()
        )

)

->when(

    $request->preset === 'this_year',

    fn ($query) => $query
        ->whereDate(
            'leads.created_at',
            '>=',
            now()->startOfYear()
        )
        ->whereDate(
            'leads.created_at',
            '<=',
            now()
        )

)

->when(

    $request->preset === 'custom',

    fn ($query) => $query

        ->when(

            $request->date_from,

            fn ($query) => $query->whereDate(
                'leads.created_at',
                '>=',
                $request->date_from
            )

        )

        ->when(

            $request->date_to,

            fn ($query) => $query->whereDate(
                'leads.created_at',
                '<=',
                $request->date_to
            )

        )

)
    ->chunk(500, function ($rows) use ($handle) {

                    foreach ($rows as $row) {

                        fputcsv($handle, [

                            $row->id,
                            $row->created_at,
                            $row->lead_status,
                            $row->source,
                            $row->utm_source,
                            $row->utm_campaign,
                            $row->name,
                            $row->phone,
                            $row->email,
                            $row->order_status,
                            $row->total_price,

                        ]);
                    }
                });

            fclose($handle);

        }, $fileName);
    }
}```

## Файл: app/Filament/Pages/Exports/LeadExport.php
```php
<?php

namespace App\Filament\Pages\Exports;

use App\Services\Leads\LeadQueryService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Tables;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Notifications\Notification;


class LeadExport extends Page implements HasTable
{
    use InteractsWithTable;

    /**
     * ---------------------------------------------------------
     * FILAMENT + SHIELD + SPATIE PERMISSION
     * ---------------------------------------------------------
     * ACCESS ONLY THROUGH PERMISSIONS
     * DO NOT USE hasRole()
     * ---------------------------------------------------------
     */

    protected Width|string|null $maxContentWidth = Width::Full;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-arrow-down';

    protected static ?string $navigationLabel = 'Lead Export';

    protected static ?string $title = 'Lead Export';

    protected static string|\UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.exports.lead-export';

 /**
 * ---------------------------------------------------------
 * STACK / PROJECT STANDARD
 * ---------------------------------------------------------
 * Laravel 13.11.2
 * Livewire 3.8.0
 * Filament 4.11.5
 * Filament Shield 4.2.0
 * Spatie Permission 7.4.1
 * ---------------------------------------------------------
 * ACCESS:
 * Використовувати тільки Shield permissions.
 * НЕ використовувати hasRole().
 * ---------------------------------------------------------
 * FILAMENT 4 FILTERS API:
 * - HasFiltersForm
 * - Filament\Schemas\Schema
 * - filtersForm(Schema $schema): Schema
 *
 * НЕ використовувати:
 * - Filament\Forms\Form
 * - getFiltersFormSchema()
 * ---------------------------------------------------------
 */

    public static function canAccess(): bool
    {
        return auth()->user()?->can('View:LeadExport') ?? false;
    }

    /**
     * ---------------------------------------------------------
     * SIDEBAR NAVIGATION
     * ---------------------------------------------------------
     */
    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->can('View:LeadExport') ?? false;
    }

    /**
     * ---------------------------------------------------------
     * TABLE
     * ---------------------------------------------------------
     */
    public function table(Table $table): Table
    {
        return $table

            /**
             * ---------------------------------------------------------
             * QUERY
             * ---------------------------------------------------------
             */
            ->query(
                app(LeadQueryService::class)->getQuery()
            )

            /**
             * ---------------------------------------------------------
             * FILTERS
             * ---------------------------------------------------------
             */

            ->filters([

                Filter::make('date_range')

                    ->form([

                        Select::make('preset')
                            ->label('Період')
                            ->default('today')
                            ->live()

                            ->options([

                                'today' => 'Сьогодні',
                                'yesterday' => 'Вчора',
                                'this_month' => 'Поточний місяць',
                                'last_30_days' => 'Останні 30 днів',
                                'this_year' => 'Поточний рік',
                                'custom' => 'Свій варіант',

                            ])

                            ->afterStateUpdated(function ($state, callable $set) {

                                match ($state) {

                                    'today' => [
                                        $set('date_from', now()->startOfDay()),
                                        $set('date_to', now()->endOfDay()),
                                    ],

                                    'yesterday' => [
                                        $set('date_from', now()->subDay()->startOfDay()),
                                        $set('date_to', now()->subDay()->endOfDay()),
                                    ],

                                    'this_month' => [
                                        $set('date_from', now()->startOfMonth()),
                                        $set('date_to', now()->endOfMonth()),
                                    ],

                                    'last_30_days' => [
                                        $set('date_from', now()->subDays(30)),
                                        $set('date_to', now()),
                                    ],

                                    'this_year' => [
                                        $set('date_from', now()->startOfYear()),
                                        $set('date_to', now()->endOfYear()),
                                    ],

                                    default => null,
                                };
                            }),

                        DatePicker::make('date_from')
                            ->label('Дата від')
                            ->default(now()->startOfYear()),

                        DatePicker::make('date_to')
                            ->label('Дата до')
                            ->default(now()),

                    ])

                    ->query(function (Builder $query, array $data): Builder {

                        $preset = $data['preset'] ?? 'this_year';

                        return match ($preset) {

                            'today' => $query->whereDate(
                                'leads.created_at',
                                today()
                            ),

                            'yesterday' => $query->whereDate(
                                'leads.created_at',
                                today()->subDay()
                            ),

                            'this_month' => $query
                                ->whereDate('leads.created_at', '>=', now()->startOfMonth())
                                ->whereDate('leads.created_at', '<=', now()),

                            'last_30_days' => $query
                                ->whereDate('leads.created_at', '>=', now()->subDays(30))
                                ->whereDate('leads.created_at', '<=', now()),

                            'custom' => $query
                                ->when(
                                    $data['date_from'],
                                    fn (Builder $query, $date): Builder => $query->whereDate(
                                        'leads.created_at',
                                        '>=',
                                        Carbon::parse($date)
                                    )
                                )
                                ->when(
                                    $data['date_to'],
                                    fn (Builder $query, $date): Builder => $query->whereDate(
                                        'leads.created_at',
                                        '<=',
                                        Carbon::parse($date)
                                    )
                                ),

                            default => $query
                                ->whereDate('leads.created_at', '>=', now()->startOfYear())
                                ->whereDate('leads.created_at', '<=', now()),
                        };
                    }),

            ])

            /**
             * ---------------------------------------------------------
             * TABLE SETTINGS
             * ---------------------------------------------------------
             */
            ->deferLoading()

            ->defaultSort('leads.id', 'desc')

            ->paginated([25, 50, 100])

            ->striped()

            /**
             * ---------------------------------------------------------
             * COLUMNS
             * ---------------------------------------------------------
             */
            ->columns([

                /**
                 * ---------------------------------------------------------
                 * №
                 * ---------------------------------------------------------
                 */
                TextColumn::make('id')
                    ->label('№')
                    ->sortable(),

                /**
                 * ---------------------------------------------------------
                 * ЧАС
                 * ---------------------------------------------------------
                 */
                TextColumn::make('created_at')
                    ->label('час')
                    ->dateTime('d.m.Y H:i:s')
                    ->sortable(),

                /**
                 * ---------------------------------------------------------
                 * ДЖЕРЕЛО ТРАФІКУ
                 * ---------------------------------------------------------
                 */
                TextColumn::make('utm_source')
                    ->label('джерело трафіку'),

                /**
                 * ---------------------------------------------------------
                 * ПОДІЯ
                 * ---------------------------------------------------------
                 */
                TextColumn::make('source')
                    ->label('подія')

                    ->formatStateUsing(function (?string $state): string {

                        return match ($state) {

                            'call' => 'Дзвінок',
                            'office-visit' => 'Візит в офіс',
                            'binotel_chat' => 'Binotel chat',
                            'site' => 'Заявка з сайту',
                            'get_call_binotel' => 'Зворотній дзвінок',
                            'fb_lid' => 'Facebook lead',
                            'fb_chat' => 'Facebook chat',

                            default => '-',
                        };
                    })

                    ->badge(),

                /**
                 * ---------------------------------------------------------
                 * ЦІЛЬОВИЙ
                 * ---------------------------------------------------------
                 */
                BadgeColumn::make('lead_target_status')

                    ->label('цільовий')

                    ->state(function ($record) {

                        return $record->lead_status;
                    })

                    ->formatStateUsing(function (?string $state): string {

                        return match ($state) {

                            'processing',
                            'zamir',
                            'vizyt_ofis',
                            'accepted',
                            'measuring' => 'цільовий',

                            'not_targeted',
                            'another_city',
                            'reklamatsiya_amtech',
                            'reklamatsiya' => 'не цільовий',

                            'new' => 'невідомо',

                            default => 'інше',
                        };
                    })

                    ->color(function (?string $state): string {

                        return match ($state) {

                            'processing',
                            'zamir',
                            'vizyt_ofis',
                            'accepted',
                            'measuring' => 'success',

                            'not_targeted',
                            'another_city',
                            'reklamatsiya_amtech',
                            'reklamatsiya' => 'danger',

                            'new' => 'gray',

                            default => 'warning',
                        };
                    }),

                /**
                 * ---------------------------------------------------------
                 * СТАТУС
                 * ---------------------------------------------------------
                 */
                BadgeColumn::make('lead_status_group')

                    ->label('статус')

                    ->state(function ($record) {

                        return $record->lead_status;
                    })

                    ->formatStateUsing(function (?string $state): string {

                        return match ($state) {

                            'new' => 'новий',

                            'processing',
                            'zamir',
                            'vizyt_ofis',
                            'measuring' => 'в роботі',

                            'accepted' => 'продано',

                            'canceled',
                            'not_targeted',
                            'another_city',
                            'propushcheno',
                            'reklamatsiya',
                            'reklamatsiya_amtech' => 'скасовано',

                            default => 'невідомо',
                        };
                    })

                    ->color(function (?string $state): string {

                        return match ($state) {

                            'new' => 'gray',

                            'processing',
                            'zamir',
                            'vizyt_ofis',
                            'measuring' => 'warning',

                            'accepted' => 'success',

                            'canceled',
                            'not_targeted',
                            'another_city',
                            'propushcheno',
                            'reklamatsiya',
                            'reklamatsiya_amtech' => 'danger',

                            default => 'gray',
                        };
                    }),

                /**
                 * ---------------------------------------------------------
                 * ІМʼЯ
                 * ---------------------------------------------------------
                 */
                TextColumn::make('name')
                    ->label('імʼя')
                    ->searchable(),

                /**
                 * ---------------------------------------------------------
                 * EMAIL
                 * ---------------------------------------------------------
                 */
                TextColumn::make('email')
                    ->label('email')
                    ->toggleable(isToggledHiddenByDefault: true),

                /**
                 * ---------------------------------------------------------
                 * ТЕЛЕФОН
                 * ---------------------------------------------------------
                 */
                TextColumn::make('phone')
                    ->label('телефон'),

                /**
                 * ---------------------------------------------------------
                 * СУМА
                 * ---------------------------------------------------------
                 */
                TextColumn::make('total_price')
                    ->label('сума')
                    ->money('UAH', divideBy: 1),

                /**
                 * ---------------------------------------------------------
                 * КАМПАНІЯ
                 * ---------------------------------------------------------
                 */
                TextColumn::make('utm_campaign')
                    ->label('кампанія')
                    ->wrap()
                    ->limit(40),

                /**
                 * ---------------------------------------------------------
                 * GCLID
                 * ---------------------------------------------------------
                 */
                TextColumn::make('gclid')

                    ->label('gclid')

                    ->state(function ($record): string {

                        return filled($record->gclid)
                            ? 'отримано'
                            : 'не отримано';
                    })

                    ->badge()

                    ->color(function ($record): string {

                        return filled($record->gclid)
                            ? 'success'
                            : 'gray';
                    })

                    ->toggleable(isToggledHiddenByDefault: true),

            ]);
    }

    /**
     * ---------------------------------------------------------
     * HEADER ACTIONS
     * ---------------------------------------------------------
     */
   protected function getHeaderActions(): array
{
    return [

        Action::make('exportCsv')

            ->label('Export CSV')

            ->icon('heroicon-o-arrow-down-tray')

            ->color('warning')

            /**
             * ---------------------------------------------------------
             * FILAMENT LOADING STATE
             * ---------------------------------------------------------
             */
            ->url(function (): string {

    /**
     * ---------------------------------------------------------
     * GET ACTIVE DATE FILTER
     * ---------------------------------------------------------
     */
    $filters = $this->tableFilters['date_range'] ?? [];

    return route('lead-export.page', [

        'preset' => $filters['preset'] ?? null,

        'date_from' => $filters['date_from'] ?? null,

        'date_to' => $filters['date_to'] ?? null,

    ]);
})

->openUrlInNewTab(),

    ];
}
}```

## Файл: app/Services/Leads/LeadQueryService.php
```php
<?php

namespace App\Services\Leads;

use App\Models\Lead;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class LeadQueryService
{
    

/**
 * ---------------------------------------------------------
 * STACK / PROJECT STANDARD
 * ---------------------------------------------------------
 * Laravel 13.11.2
 * Livewire 3.8.0
 * Filament 4.11.5
 * Filament Shield 4.2.0
 * Spatie Permission 7.4.1
 * ---------------------------------------------------------
 * ACCESS:
 * Використовувати тільки Shield permissions.
 * НЕ використовувати hasRole().
 * ---------------------------------------------------------
 * FILAMENT 4 FILTERS API:
 * - HasFiltersForm
 * - Filament\Schemas\Schema
 * - filtersForm(Schema $schema): Schema
 *
 * НЕ використовувати:
 * - Filament\Forms\Form
 * - getFiltersFormSchema()
 * ---------------------------------------------------------
 */

/**
     * ---------------------------------------------------------
     * GET QUERY
     * ---------------------------------------------------------
     * ONLY READ DATA
     * NO INSERT / UPDATE / DELETE
     * ---------------------------------------------------------
     */
    public function getQuery(): Builder
    {
        return Lead::query()

            ->from('leads')

            ->leftJoin(
                'clients',
                'clients.id',
                '=',
                'leads.client_id'
            )

            ->leftJoin(
                'orders',
                'orders.lead_id',
                '=',
                'leads.id'
            )

            ->select([

                'leads.id',

                'leads.source',

                'leads.created_at',

                'leads.status as lead_status',

                'leads.comment',

                'leads.comment_callback',

                'leads.utm_source',

                'leads.utm_campaign',

                'leads.utm_medium',

                'leads.gclid',

                'clients.name',

                'clients.phone',

                'clients.email',

                'orders.total_price',

                'orders.success_date',

                'orders.status as order_status',
            ]);
    }

    /**
 * Застосовує фільтри по датах
 */
public function applyDateFilters(Builder $query, array $filters): Builder
{
    $preset = $filters['preset'] ?? 'this_year';
    $dateFrom = $filters['date_from'] ?? null;
    $dateTo = $filters['date_to'] ?? null;

    if ($dateFrom && $dateTo) {
        // Якщо дати явно вказані — пріоритет за ними
        return $query
            ->whereDate('leads.created_at', '>=', Carbon::parse($dateFrom))
            ->whereDate('leads.created_at', '<=', Carbon::parse($dateTo));
    }

    // Fallback на пресет
    return match ($preset) {
        'today' => $query->whereDate('leads.created_at', today()),
        'yesterday' => $query->whereDate('leads.created_at', today()->subDay()),
        'this_month' => $query
            ->whereDate('leads.created_at', '>=', now()->startOfMonth())
            ->whereDate('leads.created_at', '<=', now()),
        'last_30_days' => $query
            ->whereDate('leads.created_at', '>=', now()->subDays(30))
            ->whereDate('leads.created_at', '<=', now()),
        default => $query  // this_year
            ->whereDate('leads.created_at', '>=', now()->startOfYear())
            ->whereDate('leads.created_at', '<=', now()),
    };
}
}```

## Файл: resources/views/filament/pages/exports/lead-export.blade.php
```php
<x-filament-panels::page>

    {{ $this->table }}

</x-filament-panels::page>
```

## Файл: resources/views/exports/lead-export-loading.blade.php
```php
<!DOCTYPE html>
<html lang="en">
<head>

    <meta charset="UTF-8">

    <title>Preparing CSV export...</title>

    <style>

        /**
         * ---------------------------------------------------------
         * PAGE
         * ---------------------------------------------------------
         */
        body {
            margin: 0;
            padding: 0;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #0f172a;
            color: white;
            font-family: Arial, sans-serif;
        }

        /**
         * ---------------------------------------------------------
         * CONTENT WRAPPER
         * ---------------------------------------------------------
         */
        .wrapper {
            text-align: center;
            max-width: 500px;
            padding: 30px;
        }

        /**
         * ---------------------------------------------------------
         * SPINNER
         * ---------------------------------------------------------
         */
        .spinner {
            width: 64px;
            height: 64px;
            border: 5px solid rgba(255,255,255,0.15);
            border-top-color: #facc15;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 24px;
        }

        /**
         * ---------------------------------------------------------
         * SPINNER ANIMATION
         * ---------------------------------------------------------
         */
        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /**
         * ---------------------------------------------------------
         * TITLE
         * ---------------------------------------------------------
         */
        h1 {
            margin: 0 0 14px;
            font-size: 24px;
            font-weight: 700;
        }

        /**
         * ---------------------------------------------------------
         * DESCRIPTION
         * ---------------------------------------------------------
         */
        p {
            margin: 0;
            opacity: 0.75;
            line-height: 1.6;
            font-size: 15px;
        }

    </style>

</head>

<body>

<div class="wrapper">

    <div class="spinner"></div>

    <h1>Preparing CSV export...</h1>

    <p>
        Please wait while the browser generates and downloads the CSV file.
        <br><br>
        This window will close automatically in a few seconds.
    </p>

</div>

<script>

    setTimeout(() => {

        window.location.href =
            "{{ route('lead-export.csv') }}"
            + "?preset={{ $preset }}"
            + "&date_from={{ $date_from }}"
            + "&date_to={{ $date_to }}";

    }, 300);

    setTimeout(() => {

        window.close();

    }, 5000);

</script>

</body>
</html>```

## Файл: resources/views/filament/pages/dashboard/marketing-agency-dashboard.blade.php
```php
{{-- 
---------------------------------------------------------
STACK / PROJECT STANDARD
---------------------------------------------------------
Laravel 13.11.2
Livewire 3.8.0
Filament 4.11.5
Blade + Alpine.js
---------------------------------------------------------
--}}

<x-filament-panels::page>

    {{-- 
    ---------------------------------------------------------
    FILTERS
    ---------------------------------------------------------
    --}}
    <x-filament::section class="mb-6">

        <div class="flex flex-col gap-4">

            <div class="w-full max-w-md">

                {{ $this->filtersForm }}

            </div>

            {{-- 
            ---------------------------------------------------------
            LOADING
            ---------------------------------------------------------
            --}}
            <div
                wire:loading.flex
                wire:target="filters"
                class="items-center gap-2 text-sm text-warning-600"
            >

                <x-filament::loading-indicator class="h-5 w-5" />

                <span>Оновлення даних...</span>

            </div>

        </div>

    </x-filament::section>

    {{-- 
    ---------------------------------------------------------
    WIDGETS
    ---------------------------------------------------------
    --}}
    <div
        wire:loading.class="opacity-50"
        wire:target="filters"
        class="transition duration-300"
        x-data="{ refreshKey: 0 }"
        x-on:refresh-widgets.window="refreshKey++; $wire.$refresh()"
    >

        <x-filament-widgets::widgets
            :columns="2"
            :widgets="$this->getWidgets()"
            x-bind:key="refreshKey"
        />

    </div>

    <script>
        document.addEventListener('livewire:initialized', () => {
            Livewire.on('refresh-widgets', () => {
                window.dispatchEvent(new CustomEvent('refresh-widgets'));
            });
        });
    </script>

</x-filament-panels::page>```

