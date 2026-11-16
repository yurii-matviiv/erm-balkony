<?php

namespace App\Filament\Manager\Pages;

/** Заглушка: «Не прийняті» зі старої CRM (/binotel/unsuccessful-calls). */
class UnacceptedCalls extends PlaceholderPage
{
    protected static ?string $navigationLabel = 'Не прийняті';

    protected static ?string $title = 'Не прийняті';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-phone-x-mark';

    protected static \UnitEnum|string|null $navigationGroup = 'Менеджер';

    protected static ?int $navigationSort = 50;

    protected static string $legacyRoute = '/binotel/unsuccessful-calls';
}
