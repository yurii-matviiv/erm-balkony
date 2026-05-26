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
}