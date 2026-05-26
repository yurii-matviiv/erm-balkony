<?php

namespace App\Filament\Pages\Dashboard;

use Filament\Pages\Page;

class AccountantDashboard extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Accountant Dashboard';

    protected static ?string $title = 'Accountant Dashboard';

    protected string $view = 'filament.pages.dashboard.accountant-dashboard';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('accountant') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasRole('accountant') ?? false;
    }
}
