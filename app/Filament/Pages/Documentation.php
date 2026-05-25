<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class Documentation extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Documentation';

    protected static ?string $title = 'Documentation';

    protected string $view = 'filament.pages.documentation';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin');
    }
}
