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
}