<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                /**
                 * ---------------------------------------------------------
                 * NAME
                 * ---------------------------------------------------------
                 */
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                /**
                 * ---------------------------------------------------------
                 * EMAIL
                 * ---------------------------------------------------------
                 */
                TextInput::make('email')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),

                /**
                 * ---------------------------------------------------------
                 * PASSWORD
                 * ---------------------------------------------------------
                 */
                TextInput::make('password')
                    ->password()
                    ->revealable()

                    ->required(fn (string $operation): bool => $operation === 'create')

                    ->dehydrated(fn (?string $state): bool => filled($state))

                    ->dehydrateStateUsing(fn (string $state): string => bcrypt($state))

                    ->maxLength(255),

                /**
                 * ---------------------------------------------------------
                 * ROLES
                 * ---------------------------------------------------------
                 */
                Select::make('roles')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable(),
            ]);
    }
}