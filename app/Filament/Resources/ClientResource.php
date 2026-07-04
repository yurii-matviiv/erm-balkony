<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClientResource\Pages\CreateClient;
use App\Filament\Resources\ClientResource\Pages\EditClient;
use App\Filament\Resources\ClientResource\Pages\ListClients;
use App\Models\Client;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Simple CRUD for clients: just their contact data for now (name, phones,
 * email, viber, address, comment, who they're attributed to). No business
 * logic (deals/orders/status pipeline) yet — this is intentionally the
 * first, minimal version, per the initial request: get the existing
 * contact data visible and editable first, build the rest later.
 */
class ClientResource extends Resource
{
    protected static ?string $model = Client::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationLabel = 'Клієнти';

    protected static ?string $modelLabel = 'клієнт';

    protected static ?string $pluralModelLabel = 'клієнти';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            // No separate "old name" field anymore — the old system's
            // single free-text name lives in `first_name` now (see
            // ClientsSyncMapper), so for old synced clients it's normal
            // to see their whole name sitting unsplit in "Ім'я" below
            // until someone manually splits it into Прізвище/По батькові.
            TextInput::make('last_name')->label('Прізвище')->maxLength(255),
            TextInput::make('first_name')->label("Ім'я")->maxLength(255),
            TextInput::make('middle_name')->label('По батькові')->maxLength(255),

            TextInput::make('phone')
                ->label('Телефон')
                ->tel()
                ->required()
                ->maxLength(30),

            TextInput::make('phone2')
                ->label('Телефон (додатковий)')
                ->tel()
                ->maxLength(30),

            TextInput::make('email')
                ->label('Email')
                ->email()
                ->maxLength(255),

            TextInput::make('viber')
                ->label('Viber')
                ->maxLength(30),

            TextInput::make('address')
                ->label('Адреса (старе, одне поле)')
                ->maxLength(255)
                ->helperText('Залишено для клієнтів зі старої бази. Для нових — поля нижче.'),

            TextInput::make('city')->label('Місто')->default('Київ')->maxLength(255),
            TextInput::make('street')->label('Вулиця')->maxLength(255),
            TextInput::make('house_number')->label('Будинок')->maxLength(20),
            TextInput::make('apartment_number')->label('Квартира')->maxLength(20),
            TextInput::make('floor')->label('Поверх')->maxLength(20),

            Select::make('caller_type')
                ->label('Тип')
                ->options([
                    'client' => 'Клієнт',
                    'supplier' => 'Постачальник',
                    'spam' => 'Спам',
                    'other' => 'Інше',
                ]),

            Select::make('manager_id')
                ->label('Менеджер')
                ->relationship('manager', 'name')
                ->searchable()
                ->preload(),

            Textarea::make('comment')
                ->label('Коментар')
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_name')
                    ->label("Ім'я")
                    ->searchable(['last_name', 'first_name', 'middle_name']),

                TextColumn::make('phone')
                    ->label('Телефон')
                    ->searchable(),

                TextColumn::make('phone2')
                    ->label('Телефон 2')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('viber')
                    ->label('Viber')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('full_address')
                    ->label('Адреса')
                    ->toggleable()
                    ->limit(40),

                TextColumn::make('caller_type')
                    ->label('Тип')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'client' => 'Клієнт',
                        'supplier' => 'Постачальник',
                        'spam' => 'Спам',
                        'other' => 'Інше',
                        default => '—',
                    }),

                TextColumn::make('manager.name')
                    ->label('Менеджер')
                    ->placeholder('—'),

                TextColumn::make('created_at')
                    ->label('Створено')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('caller_type')
                    ->label('Тип')
                    ->options([
                        'client' => 'Клієнт',
                        'supplier' => 'Постачальник',
                        'spam' => 'Спам',
                        'other' => 'Інше',
                    ]),
                SelectFilter::make('manager_id')
                    ->label('Менеджер')
                    ->relationship('manager', 'name'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClients::route('/'),
            'create' => CreateClient::route('/create'),
            'edit' => EditClient::route('/{record}/edit'),
        ];
    }
}
