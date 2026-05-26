<?php

namespace App\Filament\Pages\Dashboard;

use Filament\Pages\Page;

class AccountantDashboard extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Accountant Dashboard';

    protected static ?string $title = 'Accountant Dashboard';

    protected string $view = 'filament.pages.dashboard.accountant-dashboard';

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
        // Перевіряємо наявність дозволу 'view_accountant_dashboard'
        return auth()->user()?->can('view_accountant_dashboard') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        // Перевіряємо наявність дозволу 'view_accountant_dashboard'
        return auth()->user()?->can('view_accountant_dashboard') ?? false;
    }
}