<?php

namespace App\Filament\Pages\Dashboard;

use Filament\Pages\Page;
use Filament\Support\Enums\Width;

class AdminDashboard extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Admin Dashboard';

    protected static ?string $title = 'Admin Dashboard';

    protected static string|\UnitEnum|null $navigationGroup = 'Dashboard';

    protected string $view = 'filament.pages.dashboard.admin-dashboard';

    protected Width|string|null $maxContentWidth = Width::Full;

    /**
     * ---------------------------------------------------------
     * ACCESS
     * ---------------------------------------------------------
     */
    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    /**
     * ---------------------------------------------------------
     * SIDEBAR NAVIGATION
     * ---------------------------------------------------------
     */
    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }
}