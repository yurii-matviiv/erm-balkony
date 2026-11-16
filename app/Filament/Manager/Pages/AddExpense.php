<?php

namespace App\Filament\Manager\Pages;

/** Заглушка: «Додати витрату» зі старої CRM (/debit/expense_add). */
class AddExpense extends PlaceholderPage
{
    protected static ?string $navigationLabel = 'Додати витрату';

    protected static ?string $title = 'Додати витрату';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-plus-circle';

    protected static \UnitEnum|string|null $navigationGroup = 'Менеджер';

    protected static ?int $navigationSort = 70;

    protected static string $legacyRoute = '/debit/expense_add';
}
