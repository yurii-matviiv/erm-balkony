<?php

namespace App\Filament\Pages\Dashboard;

use Filament\Pages\Page;

class SalesManagerDashboard extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Sales Manager Dashboard';

    protected static ?string $title = 'Sales Manager Dashboard';

    protected string $view = 'filament.pages.dashboard.sales-manager-dashboard';

    /**
     * ---------------------------------------------------------
     * FILAMENT SHIELD + SPATIE PERMISSION
     * ---------------------------------------------------------
     * ACCESS ONLY THROUGH PERMISSIONS
     * DO NOT USE hasRole()
     * ---------------------------------------------------------
     */

    public static function canAccess(): bool
    {
        // Замініть 'view_sales_manager_dashboard' на відповідний дозвіл, 
        // налаштований у вашому Filament Shield
        return auth()->user()?->can('view_sales_manager_dashboard') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->can('view_sales_manager_dashboard') ?? false;
    }
}