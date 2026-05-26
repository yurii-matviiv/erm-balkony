<?php

namespace App\Filament\Pages\Dashboard;

use Filament\Pages\Page;

class InstallerDashboard extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Installer Dashboard';

    protected static ?string $title = 'Installer Dashboard';

    protected string $view = 'filament.pages.dashboard.installer-dashboard';

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
        // Перевіряємо наявність дозволу 'view_installer_dashboard'
        return auth()->user()?->can('view_installer_dashboard') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        // Перевіряємо наявність дозволу 'view_installer_dashboard'
        return auth()->user()?->can('view_installer_dashboard') ?? false;
    }
}