<?php

namespace App\Filament\Pages\Dashboard;

use Filament\Pages\Page;

class SalesManagerDashboard extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Sales Manager Dashboard';

    protected static ?string $title = 'Sales Manager Dashboard';

    protected string $view = 'filament.pages.dashboard.sales-manager-dashboard';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('sales_manager') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasRole('sales_manager') ?? false;
    }
}
