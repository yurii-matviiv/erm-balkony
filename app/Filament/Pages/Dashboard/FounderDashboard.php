<?php

namespace App\Filament\Pages\Dashboard;

use Filament\Pages\Page;

class FounderDashboard extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Founder Dashboard';

    protected static ?string $title = 'Founder Dashboard';

    protected string $view = 'filament.pages.dashboard.founder-dashboard';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('founder') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasRole('founder') ?? false;
    }
}
