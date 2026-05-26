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
}