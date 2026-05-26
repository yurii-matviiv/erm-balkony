<?php

namespace App\Filament\Widgets\Marketing;

use App\Models\Lead;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class LeadStatsWidget extends StatsOverviewWidget
{
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
        /**
         * ---------------------------------------------------------
         * BASE QUERY
         * ---------------------------------------------------------
         */
        $baseQuery = Lead::query()
            ->whereYear('created_at', now()->year);

        /**
         * ---------------------------------------------------------
         * TARGET STATUSES
         * ---------------------------------------------------------
         */
        $targetStatuses = [
            'processing',
            'zamir',
            'vizyt_ofis',
            'accepted',
            'measuring',
        ];

        /**
         * ---------------------------------------------------------
         * NOT TARGET STATUSES
         * ---------------------------------------------------------
         */
        $notTargetStatuses = [
            'not_targeted',
            'another_city',
            'reklamatsiya_amtech',
            'reklamatsiya',
        ];

        /**
         * ---------------------------------------------------------
         * TOTAL
         * ---------------------------------------------------------
         */
        $totalLeads = (clone $baseQuery)->count();

        /**
         * ---------------------------------------------------------
         * TARGET
         * ---------------------------------------------------------
         */
        $targetLeads = (clone $baseQuery)
            ->whereIn('status', $targetStatuses)
            ->count();

        /**
         * ---------------------------------------------------------
         * NOT TARGET
         * ---------------------------------------------------------
         */
        $notTargetLeads = (clone $baseQuery)
            ->whereIn('status', $notTargetStatuses)
            ->count();

        /**
         * ---------------------------------------------------------
         * UNKNOWN
         * ---------------------------------------------------------
         * ВСЕ ЩО НЕ ВХОДИТЬ В target + not_target
         * ---------------------------------------------------------
         */
        $unknownLeads = (clone $baseQuery)
            ->whereNotIn(
                'status',
                array_merge(
                    $targetStatuses,
                    $notTargetStatuses
                )
            )
            ->count();

        /**
         * ---------------------------------------------------------
         * ACCEPTED / SOLD
         * ---------------------------------------------------------
         */
        $acceptedLeads = (clone $baseQuery)
            ->where('status', 'accepted')
            ->count();

        return [

            Stat::make(
                'Всього лідів',
                $totalLeads
            ),

            Stat::make(
                'Цільові',
                $targetLeads
            ),

            Stat::make(
                'Не цільові',
                $notTargetLeads
            ),

            Stat::make(
                'Невідомо',
                $unknownLeads
            ),

            Stat::make(
                'Продані',
                $acceptedLeads
            ),

        ];
    }
}