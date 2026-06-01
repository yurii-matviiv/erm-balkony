# Контекст проекту
**Дата збору:** 2026-06-01 19:16:21
---

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

    
    protected ?string $heading = 'Trend за типами замовлень на основі ліда';
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
 * 
 * ВАЖЛИВО ПРО СТАТУСИ ЛІДІВ (оновлено 2026-05-26):
 * ---------------------------------------------------------
 * Цільові статуси (потенційно могли купити):
 * - processing, zamir, vizyt_ofis, accepted, measuring, canceled, for_later
 * 
 * Не цільові статуси (відсіяні на старті):
 * - not_targeted, another_city, reklamatsiya_amtech, reklamatsiya
 * 
 * Нові (ще не оброблені):
 * - new
 * 
 * Продані (успішні угоди):
 * - accepted
 * 
 * canceled та for_later додані до цільових, тому що:
 * - canceled: лід був цільовим, але не дійшов до замовлення
 * - for_later: лід цільовий, але відкладений на потім
 * 
 * Конверсія = (Продані / Всього цільових) * 100%
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
     * Цільові статуси (оновлено):
     * - processing, zamir, vizyt_ofis, accepted, measuring, canceled, for_later
     * 
     * Не цільові статуси:
     * - not_targeted, another_city, reklamatsiya_amtech, reklamatsiya
     * 
     * Нові:
     * - new
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
         * ПІДРАХУНОК КАТЕГОРІЙ (оновлено)
         * Цільові тепер включають processing, zamir, vizyt_ofis, accepted, 
         * measuring, canceled, for_later
         */
        $targetCount = $rows->whereIn('status', [
            'processing',
            'zamir',
            'vizyt_ofis',
            'accepted',
            'measuring',
            'canceled',
            'for_later',
        ])->count();
        
        $soldCount = $rows->where('status', 'accepted')->count();
        
        // Конверсія: продані / всі цільові * 100%
        $conversionRate = $targetCount > 0 ? round(($soldCount / $targetCount) * 100) : 0;

        /**
         * ФОРМУВАННЯ СТАТИСТИКИ
         * Додано метрику Конверсія
         */
        return [
            Stat::make('Всього лідів', $rows->count()),
            
            Stat::make(
                'Цільові',
                $targetCount
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
                'Нові',
                $rows->where('status', 'new')->count()
            ),
            
            Stat::make(
                'Продані',
                $soldCount
            ),
            
            Stat::make(
                'Продані / Всього з цільових ',
                $conversionRate . '%'
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

    
    protected ?string $heading = 'Trend за типами замовлень на основі ліда';
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
 * 
 * ВАЖЛИВО ПРО СТАТУСИ ЛІДІВ (оновлено 2026-05-26):
 * ---------------------------------------------------------
 * Цільові статуси (потенційно могли купити):
 * - processing, zamir, vizyt_ofis, accepted, measuring, canceled, for_later
 * 
 * Не цільові статуси (відсіяні на старті):
 * - not_targeted, another_city, reklamatsiya_amtech, reklamatsiya
 * 
 * Нові (ще не оброблені):
 * - new
 * 
 * Продані (успішні угоди):
 * - accepted
 * 
 * canceled та for_later додані до цільових, тому що:
 * - canceled: лід був цільовим, але не дійшов до замовлення
 * - for_later: лід цільовий, але відкладений на потім
 * 
 * Конверсія = (Продані / Всього цільових) * 100%
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
     * Цільові статуси (оновлено):
     * - processing, zamir, vizyt_ofis, accepted, measuring, canceled, for_later
     * 
     * Не цільові статуси:
     * - not_targeted, another_city, reklamatsiya_amtech, reklamatsiya
     * 
     * Нові:
     * - new
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
         * ПІДРАХУНОК КАТЕГОРІЙ (оновлено)
         * Цільові тепер включають processing, zamir, vizyt_ofis, accepted, 
         * measuring, canceled, for_later
         */
        $targetCount = $rows->whereIn('status', [
            'processing',
            'zamir',
            'vizyt_ofis',
            'accepted',
            'measuring',
            'canceled',
            'for_later',
        ])->count();
        
        $soldCount = $rows->where('status', 'accepted')->count();
        
        // Конверсія: продані / всі цільові * 100%
        $conversionRate = $targetCount > 0 ? round(($soldCount / $targetCount) * 100) : 0;

        /**
         * ФОРМУВАННЯ СТАТИСТИКИ
         * Додано метрику Конверсія
         */
        return [
            Stat::make('Всього лідів', $rows->count()),
            
            Stat::make(
                'Цільові',
                $targetCount
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
                'Нові',
                $rows->where('status', 'new')->count()
            ),
            
            Stat::make(
                'Продані',
                $soldCount
            ),
            
            Stat::make(
                'Продані / Всього з цільових ',
                $conversionRate . '%'
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

