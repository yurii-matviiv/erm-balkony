<?php

namespace App\Filament\Pages\Dashboard;

use Filament\Pages\Page;

class MeasurerDashboard extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Measurer Dashboard';

    protected static ?string $title = 'Measurer Dashboard';

    protected string $view = 'filament.pages.dashboard.measurer-dashboard';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('measurer') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasRole('measurer') ?? false;
    }
}
