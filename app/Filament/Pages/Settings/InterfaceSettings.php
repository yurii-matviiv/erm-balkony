<?php

namespace App\Filament\Pages\Settings;

use BackedEnum;
use Filament\Pages\Page;

/**
 * Entry point for the "Налаштування" -> "Інтерфейс" nav item. Currently
 * lists just one link ("Бокова панель") — per explicit request, this is
 * deliberately its own list page (not a direct redirect) because more
 * interface-configuration links are expected to join it later.
 */
class InterfaceSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static string|\UnitEnum|null $navigationGroup = 'Налаштування';

    protected static ?string $navigationLabel = 'Інтерфейс';

    protected static ?string $title = 'Інтерфейс';

    protected static ?string $slug = 'settings/interface';

    protected string $view = 'filament.pages.settings.interface-settings';
}
