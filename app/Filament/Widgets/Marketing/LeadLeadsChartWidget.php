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
        return auth()->user()?->can('View:MarketingAgencyDashboard') || auth()->user()?->can('View:FounderDashboard') ?? false;
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

}