<?php

namespace App\Filament\Pages\Dashboard\Concerns;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;

use App\Filament\Widgets\Marketing\LeadStatsWidget;
use App\Filament\Widgets\Marketing\LeadLeadsChartWidget;
use App\Filament\Widgets\Marketing\LeadOrdersChartWidget;
use App\Filament\Widgets\Marketing\LeadOrderTypeChartWidget;
use App\Filament\Widgets\Marketing\OrderOrderTypeChartWidget;

/**
 * ---------------------------------------------------------
 * ТРЕЙТ ДЛЯ ФІЛЬТРІВ ДАШБОРДІВ
 * ---------------------------------------------------------
 * 
 * Повністю копіює логіку MarketingAgencyDashboard
 * 
 * Використовується в:
 * - AdminDashboard
 * - FounderDashboard
 * - ManagerDashboard
 * 
 * Зміни вносяться в 1 місці
 * ---------------------------------------------------------
 */

trait HasMarketingFilters
{
    use HasFiltersForm;

    public array $currentFilters = [];

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

    public function getWidgets(): array
{
    $filters = $this->filtersForm->getState() ?: [];
    
    return [
        LeadStatsWidget::make(['pageFilters' => $filters]),           // завантажується першим
        LeadLeadsChartWidget::make(['pageFilters' => $filters]),      // завантажується після
        LeadOrdersChartWidget::make(['pageFilters' => $filters]),     // завантажується після
        LeadOrderTypeChartWidget::make(['pageFilters' => $filters]),  // відкладене (lazy)
        OrderOrderTypeChartWidget::make(['pageFilters' => $filters]), // відкладене (lazy)
    ];
}

    public function getWidgetData(): array
    {
        return [
            'filters' => $this->filtersForm->getState() ?: request()->query('filters', [])
        ];
    }
}