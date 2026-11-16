<?php

namespace App\Filament\Manager\Pages;

/** Заглушка: «Модуль З/П» зі старої CRM (/salary). */
class SalaryModule extends PlaceholderPage
{
    protected static ?string $navigationLabel = 'Модуль З/П';

    protected static ?string $title = 'Модуль З/П';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-wallet';

    protected static \UnitEnum|string|null $navigationGroup = 'Менеджер';

    protected static ?int $navigationSort = 80;

    protected static string $legacyRoute = '/salary';
}
