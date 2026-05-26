<?php

namespace App\Filament\Widgets\Marketing;

use App\Models\Lead;
use Filament\Widgets\ChartWidget;

class LeadOrdersChartWidget extends ChartWidget
{
    protected ?string $heading = 'Orders trend';

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
     */
    protected function getData(): array
    {
        $data = Lead::query()

            ->selectRaw('DATE(created_at) as date')
            ->selectRaw('COUNT(*) as total')

            ->whereYear('created_at', now()->year)

            ->where('status', 'accepted')

          ->groupByRaw('DATE(created_at)')

->orderByRaw('DATE(created_at) ASC')

            ->get();

        return [

            'datasets' => [
                [
                    'label' => 'Orders',
                    'data' => $data->pluck('total')->toArray(),
                ],
            ],

            'labels' => $data->pluck('date')->toArray(),
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
}