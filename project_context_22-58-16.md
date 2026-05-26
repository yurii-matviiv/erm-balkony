# Контекст проекту
**Дата збору:** 2026-05-26 22:58:16
---

## Файл: app/Filament/Pages/Dashboard/MarketingAgencyDashboard.php
```php
<?php

namespace App\Filament\Pages\Dashboard;

use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use App\Filament\Pages\Dashboard\Concerns\HasMarketingFilters;

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
    use HasMarketingFilters;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Marketing Agency Dashboard';

    protected static ?string $title = 'Marketing Agency Dashboard';

    protected static string|\UnitEnum|null $navigationGroup = 'Dashboard';

    protected string $view = 'filament.pages.dashboard.marketing-agency-dashboard';

    protected Width|string|null $maxContentWidth = Width::Full;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('View:MarketingAgencyDashboard') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->can('View:MarketingAgencyDashboard') ?? false;
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
        /**
         * ---------------------------------------------------------
         * ВАЖЛИВО: Цей графік показує ВСІ ЛІДИ (Lead)
         * ---------------------------------------------------------
         * Без фільтрації за статусом, тому що нам потрібна динаміка
         * всіх лідів, а не тільки проданих.
         * ---------------------------------------------------------
         */
        $query = Lead::query()

            ->selectRaw('DATE(created_at) as date')
            ->selectRaw('COUNT(*) as total');
            // НЕМАЄ ->where('status', 'accepted')

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
                    'label' => 'Leads',  // Змінено з 'Orders' на 'Leads'
                    'data' => $data
                        ->pluck('total')
                        ->toArray(),
                    'borderColor' => '#3b82f6',  // Синій колір для лідів
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
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

## Файл: app/Filament/Widgets/Marketing/LeadOrderTypeChartWidget.php
```php
<?php

namespace App\Filament\Widgets\Marketing;

use App\Models\Lead;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Livewire\Attributes\On;

class LeadOrderTypeChartWidget extends ChartWidget
{
    use InteractsWithPageFilters;
    
    protected ?string $heading = 'Trend за типами замовлень';
    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return auth()->user()?->can('View:MarketingAgencyDashboard') ?? false;
    }

    protected function getData(): array
    {
        $filters = $this->pageFilters ?? $this->filters ?? [];
        $preset = $filters['preset'] ?? 'this_year';
        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;
        
        // Отримуємо всі унікальні типи замовлень
        $orderTypes = [
            'window_in_cottage' => 'Приватний будинок',
            'balcony' => 'Балкон',
            'balcony_with_takeout' => 'Балкон з виносом',
            'turnkey_balcony' => 'Балкон під ключ',
            'balcony_cladding' => 'Обшивка балкона',
            'window' => 'Вікна',
        ];
        
        $datasets = [];
        
        foreach ($orderTypes as $typeKey => $typeLabel) {
            $query = Lead::query()
                ->selectRaw('DATE(created_at) as date')
                ->selectRaw('COUNT(*) as total')
                ->where('order_type', $typeKey);
            
            // Застосовуємо фільтри дат
            if ($preset === 'today') {
                $query->whereDate('created_at', today());
            } elseif ($preset === 'yesterday') {
                $query->whereDate('created_at', today()->subDay());
            } elseif ($preset === 'this_month') {
                $query->whereDate('created_at', '>=', now()->startOfMonth())
                      ->whereDate('created_at', '<=', now());
            } elseif ($preset === 'last_30_days') {
                $query->whereDate('created_at', '>=', now()->subDays(30))
                      ->whereDate('created_at', '<=', now());
            } elseif ($preset === 'custom' && $dateFrom && $dateTo) {
                $query->whereDate('created_at', '>=', Carbon::parse($dateFrom))
                      ->whereDate('created_at', '<=', Carbon::parse($dateTo));
            } else {
                $query->whereDate('created_at', '>=', now()->startOfYear())
                      ->whereDate('created_at', '<=', now());
            }
            
            $data = $query->groupByRaw('DATE(created_at)')
                          ->orderByRaw('DATE(created_at) ASC')
                          ->get();
            
            $datasets[] = [
                'label' => $typeLabel,
                'data' => $data->pluck('total')->toArray(),
                'borderColor' => $this->getColorForType($typeKey),
                'backgroundColor' => 'transparent',
                'tension' => 0.3,
            ];
        }
        
        // Отримуємо всі дати для labels (беремо з першого датасету, який не пустий)
        $labels = [];
        foreach ($datasets as $dataset) {
            if (!empty($dataset['data'])) {
                // Потрібно отримати labels з оригінального запиту
                $query = Lead::query()
                    ->selectRaw('DATE(created_at) as date')
                    ->whereNotNull('order_type');
                
                // Застосовуємо ті самі фільтри дат
                if ($preset === 'today') {
                    $query->whereDate('created_at', today());
                } elseif ($preset === 'yesterday') {
                    $query->whereDate('created_at', today()->subDay());
                } elseif ($preset === 'this_month') {
                    $query->whereDate('created_at', '>=', now()->startOfMonth())
                          ->whereDate('created_at', '<=', now());
                } elseif ($preset === 'last_30_days') {
                    $query->whereDate('created_at', '>=', now()->subDays(30))
                          ->whereDate('created_at', '<=', now());
                } elseif ($preset === 'custom' && $dateFrom && $dateTo) {
                    $query->whereDate('created_at', '>=', Carbon::parse($dateFrom))
                          ->whereDate('created_at', '<=', Carbon::parse($dateTo));
                } else {
                    $query->whereDate('created_at', '>=', now()->startOfYear())
                          ->whereDate('created_at', '<=', now());
                }
                
                $labels = $query->groupByRaw('DATE(created_at)')
                                ->orderByRaw('DATE(created_at) ASC')
                                ->pluck('date')
                                ->toArray();
                break;
            }
        }
        
        return [
            'datasets' => $datasets,
            'labels' => $labels,
        ];
    }
    
    private function getColorForType(string $type): string
    {
        return match ($type) {
            'window_in_cottage' => '#10b981', // зелений
            'balcony' => '#f59e0b', // помаранчевий
            'balcony_with_takeout' => '#ef4444', // червоний
            'turnkey_balcony' => '#8b5cf6', // фіолетовий
            'balcony_cladding' => '#ec4898', // рожевий
            'window' => '#3b82f6', // синій
            default => '#6b7280', // сірий
        };
    }
    
    protected function getType(): string
    {
        return 'line';
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
     * ВАЖЛИВО: Цей графік показує ТІЛЬКИ ПРОДАНІ ЛІДИ (Orders)
     * Тобто зі статусом 'accepted'
     * ---------------------------------------------------------
     */
    protected function getData(): array
    {
        /**
         * ВАЖЛИВО: Додаємо фільтрацію за статусом 'accepted'
         * Це показує тільки продані ліди (замовлення)
         */
        $query = Lead::query()

            ->selectRaw('DATE(created_at) as date')
            ->selectRaw('COUNT(*) as total')
            ->where('status', 'accepted');  // ТІЛЬКИ ПРОДАНІ

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
                    'label' => 'Orders',  // Залишаємо 'Orders'
                    'data' => $data
                        ->pluck('total')
                        ->toArray(),
                    'borderColor' => '#f59e0b',  // Помаранчевий колір для замовлень
                    'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
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

## Файл: app/Filament/Widgets/Marketing/OrderOrderTypeChartWidget.php
```php
<?php

namespace App\Filament\Widgets\Marketing;

use App\Models\Order;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Livewire\Attributes\On;

class OrderOrderTypeChartWidget extends ChartWidget
{
    use InteractsWithPageFilters;
    
    protected ?string $heading = 'Тренд за типами замовлень (на основі замовлень)';
    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return auth()->user()?->can('View:MarketingAgencyDashboard') ?? false;
    }

    protected function getData(): array
    {
        $filters = $this->pageFilters ?? $this->filters ?? [];
        $preset = $filters['preset'] ?? 'this_year';
        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;
        
        // Типи замовлень для відображення
        $orderTypes = [
            'window_in_cottage' => 'Приватний будинок',
            'balcony' => 'Балкон',
            'balcony_with_takeout' => 'Балкон з виносом',
            'turnkey_balcony' => 'Балкон під ключ',
            'balcony_cladding' => 'Обшивка балкона',
            'window' => 'Вікна',
            'windows_plus_works' => 'Вікна + роботи',
            'aluminium_window' => 'Алюмінієві вікна',
            'entrance_group_pvc' => 'Вхідна група ПВХ',
            'entrance_group_aluminium' => 'Вхідна група алюміній',
            'glazing_terrace_pvc' => 'Скління тераси ПВХ',
            'glazing_terrace_aluminium' => 'Скління тераси алюміній',
            'frameless_glazing' => 'Безрамне скління',
            'sliding_system_cold' => 'Розсувна система холодна',
            'sliding_system_warm' => 'Розсувна система тепла',
        ];
        
        $datasets = [];
        
        foreach ($orderTypes as $typeKey => $typeLabel) {
            $query = Order::query()
                ->selectRaw('DATE(create_date) as date')
                ->selectRaw('COUNT(*) as total')
                ->where('order_type', $typeKey);
            
            // Застосовуємо фільтри дат (використовуємо create_date)
            if ($preset === 'today') {
                $query->whereDate('create_date', today());
            } elseif ($preset === 'yesterday') {
                $query->whereDate('create_date', today()->subDay());
            } elseif ($preset === 'this_month') {
                $query->whereDate('create_date', '>=', now()->startOfMonth())
                      ->whereDate('create_date', '<=', now());
            } elseif ($preset === 'last_30_days') {
                $query->whereDate('create_date', '>=', now()->subDays(30))
                      ->whereDate('create_date', '<=', now());
            } elseif ($preset === 'custom' && $dateFrom && $dateTo) {
                $query->whereDate('create_date', '>=', Carbon::parse($dateFrom))
                      ->whereDate('create_date', '<=', Carbon::parse($dateTo));
            } else {
                $query->whereDate('create_date', '>=', now()->startOfYear())
                      ->whereDate('create_date', '<=', now());
            }
            
            $data = $query->groupByRaw('DATE(create_date)')
                          ->orderByRaw('DATE(create_date) ASC')
                          ->get();
            
            if ($data->isNotEmpty()) {
                $datasets[] = [
                    'label' => $typeLabel,
                    'data' => $data->pluck('total')->toArray(),
                    'borderColor' => $this->getColorForType($typeKey),
                    'backgroundColor' => 'transparent',
                    'tension' => 0.3,
                ];
            }
        }
        
        // Отримуємо всі дати для labels
        $labels = [];
        $query = Order::query()
            ->selectRaw('DATE(create_date) as date')
            ->whereNotNull('order_type');
        
        if ($preset === 'today') {
            $query->whereDate('create_date', today());
        } elseif ($preset === 'yesterday') {
            $query->whereDate('create_date', today()->subDay());
        } elseif ($preset === 'this_month') {
            $query->whereDate('create_date', '>=', now()->startOfMonth())
                  ->whereDate('create_date', '<=', now());
        } elseif ($preset === 'last_30_days') {
            $query->whereDate('create_date', '>=', now()->subDays(30))
                  ->whereDate('create_date', '<=', now());
        } elseif ($preset === 'custom' && $dateFrom && $dateTo) {
            $query->whereDate('create_date', '>=', Carbon::parse($dateFrom))
                  ->whereDate('create_date', '<=', Carbon::parse($dateTo));
        } else {
            $query->whereDate('create_date', '>=', now()->startOfYear())
                  ->whereDate('create_date', '<=', now());
        }
        
        $labels = $query->groupByRaw('DATE(create_date)')
                        ->orderByRaw('DATE(create_date) ASC')
                        ->pluck('date')
                        ->toArray();
        
        return [
            'datasets' => $datasets,
            'labels' => $labels,
        ];
    }
    
    private function getColorForType(string $type): string
    {
        return match ($type) {
            'window_in_cottage' => '#10b981',
            'balcony' => '#f59e0b',
            'balcony_with_takeout' => '#ef4444',
            'turnkey_balcony' => '#8b5cf6',
            'balcony_cladding' => '#ec4898',
            'window' => '#3b82f6',
            'windows_plus_works' => '#06b6d4',
            'aluminium_window' => '#84cc16',
            'entrance_group_pvc' => '#d946ef',
            'entrance_group_aluminium' => '#f97316',
            'glazing_terrace_pvc' => '#14b8a6',
            'glazing_terrace_aluminium' => '#6366f1',
            'frameless_glazing' => '#a855f7',
            'sliding_system_cold' => '#71717a',
            'sliding_system_warm' => '#f43f5e',
            default => '#6b7280',
        };
    }
    
    protected function getType(): string
    {
        return 'line';
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
use Filament\Widgets\Concerns\InteractsWithPageFilters;

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
 * 
 * ВАЖЛИВО ПРО ФІЛЬТРИ В FILAMENT 4.11.5:
 * ---------------------------------------------------------
 * 1. Treit InteractsWithPageFilters надає властивість $pageFilters,
 *    яка автоматично отримує фільтри з дашборду через метод getWidgets(),
 *    де відбувається передача: LeadStatsWidget::make(['pageFilters' => $filters])
 * 
 * 2. Властивість $filters додана вручну як fallback для зворотньої сумісності
 * 
 * 3. Пріоритет отримання фільтрів:
 *    - спочатку $this->pageFilters (від дашборду)
 *    - потім $this->filters (вручну через mount)
 *    - потім порожній масив
 * 
 * 4. Фільтри застосовуються до запиту Lead::query() без JOINів,
 *    щоб уникнути дублювання даних через leftJoin з clients та orders
 * ---------------------------------------------------------
 */

class LeadStatsWidget extends StatsOverviewWidget
{
    use InteractsWithPageFilters;
    
    protected ?string $pollingInterval = null;
    
    /**
     * Вручну додана властивість для отримання фільтрів через mount()
     * Використовується як fallback якщо pageFilters не передано
     */
    public array $filters = [];

    /**
     * Перевірка прав доступу через Shield permissions
     */
    public static function canView(): bool
    {
        return auth()->user()?->can('View:MarketingAgencyDashboard') ?? false;
    }

    /**
     * ---------------------------------------------------------
     * ОСНОВНА ЛОГІКА ПІДРАХУНКУ СТАТИСТИКИ
     * ---------------------------------------------------------
     * 
     * Цільові статуси:
     * - processing, zamir, vizyt_ofis, accepted, measuring
     * 
     * Не цільові статуси:
     * - not_targeted, another_city, reklamatsiya_amtech, reklamatsiya
     * 
     * Невідомо:
     * - new, canceled, propushcheno, всі інші статуси
     * 
     * Продані:
     * - accepted
     * ---------------------------------------------------------
     */
    protected function getStats(): array
    {
        /**
         * ОТРИМАННЯ ФІЛЬТРІВ:
         * Пріоритет: pageFilters (від дашборду) -> filters (fallback) -> пустий масив
         * 
         * pageFilters передається з MarketingAgencyDashboard::getWidgets():
         * LeadStatsWidget::make(['pageFilters' => $filters])
         */
        $filters = $this->pageFilters ?? $this->filters ?? [];
        
        $preset = $filters['preset'] ?? 'this_year';
        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;
        
        /**
         * ВАЖЛИВО:
         * Використовуємо Lead::query() без LeadQueryService,
         * тому що LeadQueryService робить leftJoin з clients та orders,
         * що призводить до дублювання рядків і неправильних підрахунків.
         * 
         * Для статистики нам потрібні ВСІ ліди, навіть ті, що не мають
         * зв'язаних клієнтів або замовлень.
         */
        $query = Lead::query();

        /**
         * ЗАСТОСУВАННЯ ФІЛЬТРІВ ЗА ДАТАМИ
         * В залежності від вибраного preset або кастомних дат
         */
        if ($preset === 'today') {
            $query->whereDate('created_at', today());
        }
        elseif ($preset === 'yesterday') {
            $query->whereDate('created_at', today()->subDay());
        }
        elseif ($preset === 'this_month') {
            $query->whereDate('created_at', '>=', now()->startOfMonth())
                  ->whereDate('created_at', '<=', now());
        }
        elseif ($preset === 'last_30_days') {
            $query->whereDate('created_at', '>=', now()->subDays(30))
                  ->whereDate('created_at', '<=', now());
        }
        elseif ($preset === 'custom' && $dateFrom && $dateTo) {
            $query->whereDate('created_at', '>=', Carbon::parse($dateFrom))
                  ->whereDate('created_at', '<=', Carbon::parse($dateTo));
        }
        else {
            // За замовчуванням — поточний рік
            $query->whereDate('created_at', '>=', now()->startOfYear())
                  ->whereDate('created_at', '<=', now());
        }

        /**
         * ВИКОНАННЯ ЗАПИТУ ТА ПІДРАХУНОК
         */
        $rows = $query->get();

        /**
         * ФОРМУВАННЯ СТАТИСТИКИ
         * Ключове: використовуємо whereIn для груп статусів
         */
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

    /**
     * ---------------------------------------------------------
     * MOUNT METOD
     * ---------------------------------------------------------
     * Використовується для отримання фільтрів при створенні віджета
     * через Livewire механізм.
     * 
     * Це fallback на випадок, якщо pageFilters не передано.
     * ---------------------------------------------------------
     */
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

