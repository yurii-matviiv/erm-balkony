<?php

namespace App\Filament\Pages\Dashboard;

use Filament\Pages\Page;

class ManagerDashboard extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Manager Dashboard';

    protected static ?string $title = 'Manager Dashboard';

    protected string $view = 'filament.pages.dashboard.manager-dashboard';

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
        // Перевіряємо наявність дозволу 'view_manager_dashboard'
        return auth()->user()?->can('view_manager_dashboard') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        // Перевіряємо наявність дозволу 'view_manager_dashboard'
        return auth()->user()?->can('view_manager_dashboard') ?? false;
    }
}