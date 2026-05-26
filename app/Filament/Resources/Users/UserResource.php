<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Users';

    protected static ?string $modelLabel = 'User';

    protected static ?string $pluralModelLabel = 'Users';

    protected static ?int $navigationSort = 4;

    /**
     * ---------------------------------------------------------
     * FORM
     * ---------------------------------------------------------
     */
    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    /**
     * ---------------------------------------------------------
     * TABLE
     * ---------------------------------------------------------
     */
    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    /**
     * ---------------------------------------------------------
     * RELATIONS
     * ---------------------------------------------------------
     */
    public static function getRelations(): array
    {
        return [];
    }

    /**
     * ---------------------------------------------------------
     * PAGES
     * ---------------------------------------------------------
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}