<?php

namespace App\Filament\Pages\Dashboard;

use Filament\Pages\Page;

class MeasurerDashboard extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Measurer Dashboard';

    protected static ?string $title = 'Measurer Dashboard';

    protected string $view = 'filament.pages.dashboard.measurer-dashboard';

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
        // Замініть 'view_measurer_dashboard' на назву дозволу, 
        // який ви створили для цієї сторінки в Filament Shield
        return auth()->user()?->can('view_measurer_dashboard') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->can('view_measurer_dashboard') ?? false;
    }
}