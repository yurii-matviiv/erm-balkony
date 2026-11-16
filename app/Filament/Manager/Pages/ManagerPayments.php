<?php

namespace App\Filament\Manager\Pages;

/** Заглушка: «Оплати» зі старої CRM (/debit/balance). */
class ManagerPayments extends PlaceholderPage
{
    protected static ?string $navigationLabel = 'Оплати';

    protected static ?string $title = 'Оплати';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-banknotes';

    protected static \UnitEnum|string|null $navigationGroup = 'Менеджер';

    protected static ?int $navigationSort = 60;

    protected static string $legacyRoute = '/debit/balance';
}
