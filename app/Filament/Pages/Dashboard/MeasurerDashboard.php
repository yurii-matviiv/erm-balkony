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
 * STACK / PROJECT STANDARD
 * ---------------------------------------------------------
 * Laravel 13.11.2
 * Livewire 3.8.0
 * Filament 4.11.5
 * Filament Shield 4.2.0
 * Spatie Permission 7.4.1
 * ---------------------------------------------------------
 * ACCESS:
 * Використовувати тільки Shield permissions.
 * НЕ використовувати hasRole().
 * ---------------------------------------------------------
 * FILAMENT 4 FILTERS API:
 * - HasFiltersForm
 * - Filament\Schemas\Schema
 * - filtersForm(Schema $schema): Schema
 *
 * НЕ використовувати:
 * - Filament\Forms\Form
 * - getFiltersFormSchema()
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