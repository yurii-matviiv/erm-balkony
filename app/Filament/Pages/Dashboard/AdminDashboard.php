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

class AdminDashboard extends Page
{
    use HasFiltersForm;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Admin Dashboard';

    protected static ?string $title = 'Admin Dashboard';

    protected static string|\UnitEnum|null $navigationGroup = 'Dashboard';

    protected string $view = 'filament.pages.dashboard.admin-dashboard';

    protected Width|string|null $maxContentWidth = Width::Full;

    public array $currentFilters = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('View:AdminDashboard') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->can('View:AdminDashboard') ?? false;
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
}