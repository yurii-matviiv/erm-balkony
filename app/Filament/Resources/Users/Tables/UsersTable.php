<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table

            ->columns([

                /**
                 * ---------------------------------------------------------
                 * ID
                 * ---------------------------------------------------------
                 */
                TextColumn::make('id')
                    ->sortable(),

                /**
                 * ---------------------------------------------------------
                 * NAME
                 * ---------------------------------------------------------
                 */
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                /**
                 * ---------------------------------------------------------
                 * EMAIL
                 * ---------------------------------------------------------
                 */
                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),

                /**
                 * ---------------------------------------------------------
                 * ROLES
                 * ---------------------------------------------------------
                 */
                TextColumn::make('roles.name')
                    ->badge(),

                /**
                 * ---------------------------------------------------------
                 * CREATED AT
                 * ---------------------------------------------------------
                 */
                TextColumn::make('created_at')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])

            ->defaultSort('id', 'desc')

            ->filters([])

            ->recordActions([
                EditAction::make(),
            ])

            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}