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
        // Перевіряємо наявність дозволу 'view_manager_dashboard'
        return auth()->user()?->can('view_manager_dashboard') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        // Перевіряємо наявність дозволу 'view_manager_dashboard'
        return auth()->user()?->can('view_manager_dashboard') ?? false;
    }
}