<?php

namespace App\Filament\Pages\Dashboard;

use Filament\Pages\Page;

class ManagerDashboard extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Manager Dashboard';

    protected static ?string $title = 'Manager Dashboard';

    protected string $view = 'filament.pages.dashboard.manager-dashboard';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('manager') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasRole('manager') ?? false;
    }
}
