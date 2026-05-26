<?php

namespace App\Filament\Pages\Dashboard;

use Filament\Pages\Page;
use Filament\Support\Enums\Width;

use App\Filament\Widgets\Marketing\LeadLeadsChartWidget;
use App\Filament\Widgets\Marketing\LeadOrdersChartWidget;
use App\Filament\Widgets\Marketing\LeadStatsWidget;

/**
 * ---------------------------------------------------------
 * STACK / ACCESS STANDARD
 * ---------------------------------------------------------
 * Admin panel: Filament
 * Roles / permissions UI: Filament Shield
 * Permission engine: Spatie Permission
 *
 * ВАЖЛИВО:
 * Доступ до сторінок НЕ перевіряємо через hasRole().
 * Єдине джерело прав доступу — permissions від Filament Shield.
 *
 * Для цієї сторінки Shield permission:
 * View:FounderDashboard
 * ---------------------------------------------------------
 */

class FounderDashboard extends Page
{
    /**
     * ---------------------------------------------------------
     * NAVIGATION
     * ---------------------------------------------------------
     */
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Founder Dashboard';

    protected static ?string $title = 'Founder Dashboard';

    protected static string|\UnitEnum|null $navigationGroup = 'Dashboard';

    protected static ?int $navigationSort = 3;

    /**
     * ---------------------------------------------------------
     * VIEW
     * ---------------------------------------------------------
     */
    protected string $view = 'filament.pages.dashboard.founder-dashboard';

    /**
     * ---------------------------------------------------------
     * WIDTH
     * ---------------------------------------------------------
     */
    protected Width|string|null $maxContentWidth = Width::Full;

    /**
     * ---------------------------------------------------------
     * ACCESS
     * ---------------------------------------------------------
     * Filament Shield permission:
     * View:FounderDashboard
     * ---------------------------------------------------------
     */
    public static function canAccess(): bool
    {
        return auth()->user()?->can('View:FounderDashboard') ?? false;
    }

    /**
     * ---------------------------------------------------------
     * SIDEBAR NAVIGATION
     * ---------------------------------------------------------
     */
    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->can('View:FounderDashboard') ?? false;
    }

    /**
     * ---------------------------------------------------------
     * DASHBOARD WIDGETS
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