<?php

namespace App\Filament\Pages\Dashboard;

use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use App\Filament\Pages\Dashboard\Concerns\HasMarketingFilters;

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

class FounderDashboard extends Page
{
    use HasMarketingFilters;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Founder Dashboard';

    protected static ?string $title = 'Founder Dashboard';

    protected static string|\UnitEnum|null $navigationGroup = 'Dashboard';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.dashboard.founder-dashboard';

    protected Width|string|null $maxContentWidth = Width::Full;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('View:FounderDashboard') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->can('View:FounderDashboard') ?? false;
    }
}