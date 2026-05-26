<?php

namespace App\Filament\Pages\Dashboard;

use Filament\Pages\Page;

class InstallerDashboard extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Installer Dashboard';

    protected static ?string $title = 'Installer Dashboard';

    protected string $view = 'filament.pages.dashboard.installer-dashboard';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('installer') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasRole('installer') ?? false;
    }
}
