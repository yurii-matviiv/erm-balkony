<?php

namespace App\Filament\Pages\Dashboard;

use App\Filament\Widgets\Marketing\LeadLeadsChartWidget;
use App\Filament\Widgets\Marketing\LeadOrdersChartWidget;
use App\Filament\Widgets\Marketing\LeadStatsWidget;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;

class MarketingAgencyDashboard extends Page
{
    /**
     * ---------------------------------------------------------
     * FILAMENT SHIELD + SPATIE PERMISSION
     * ---------------------------------------------------------
     * ACCESS ONLY THROUGH PERMISSIONS
     * DO NOT USE hasRole()
     * ---------------------------------------------------------
     */

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Marketing Agency Dashboard';

    protected static ?string $title = 'Marketing Agency Dashboard';

    protected static string|\UnitEnum|null $navigationGroup = 'Dashboard';

    protected string $view = 'filament.pages.dashboard.marketing-agency-dashboard';

    protected Width|string|null $maxContentWidth = Width::Full;

    /**
     * ---------------------------------------------------------
     * ACCESS
     * ---------------------------------------------------------
     */
    public static function canAccess(): bool
    {
        return auth()->user()?->can('View:MarketingAgencyDashboard') ?? false;
    }

    /**
     * ---------------------------------------------------------
     * SIDEBAR NAVIGATION
     * ---------------------------------------------------------
     */
    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->can('View:MarketingAgencyDashboard') ?? false;
    }

    /**
     * ---------------------------------------------------------
     * HEADER WIDGETS
     * ---------------------------------------------------------
     */
    protected function getHeaderWidgets(): array
    {
        return [

            LeadStatsWidget::class,

            LeadLeadsChartWidget::class,

            LeadOrdersChartWidget::class,

        ];
    }
}