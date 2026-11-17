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
use Filament\Schemas\Components\Section;
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
    // The same hint is shown under the e-mail in the form and in the client card (order/lead).
    public const EMAIL_HINT_NOTIFY = 'Обов\'язкове поле: на цю адресу клієнт отримуватиме сповіщення про дату й час доставки та монтажу.';

    public const EMAIL_HINT_MARKETING = 'Також дуже важлива для маркетингу.';

    protected static ?string $model = Client::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationLabel = 'Клієнти';

    protected static ?string $modelLabel = 'клієнт';

    protected static ?string $pluralModelLabel = 'клієнти';

    /**
     * Manager panel: a manager opens the client card from his orders/leads (as in the old CRM),
     * but only for clients he works with (own orders or own leads). Admin panel sees everyone.
     */
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user && \App\Support\RolePanels::idForUser($user) === \App\Support\RolePanels::MANAGER) {
            $query->where(function ($q) use ($user): void {
                $q->whereHas('orders', fn ($o) => $o->where('manager_id', $user->id))
                    ->orWhereHas('leads', fn ($l) => $l->where('manager_id', $user->id));
            });
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            // Full width of the page column; the column itself is limited in page-width-styles (client card page).
            Section::make()->columns(3)->columnSpanFull()
                ->schema([
            // Contact type goes first, alone in its row (one third of the width); a red hint nags until chosen.
            \Filament\Schemas\Components\Grid::make(3)->columnSpanFull()->schema([
            Select::make('caller_type')
                ->label('Тип контакту')
                ->options([
                    'client' => 'Клієнт',
                    'supplier' => 'Постачальник',
                    'spam' => 'Спам',
                    'other' => 'Інше',
                ])
                ->required()
                ->live()
                ->columnSpan(1)
                ->hint(fn ($state): ?string => blank($state) ? "Обов'язково вкажіть тип контакту" : null)
                ->hintColor('danger'),
            ]),

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

            TextInput::make('viber')
                ->label('Viber')
                ->maxLength(30),

            // E-mail sits above the physical address.
            TextInput::make('email')
                ->label('Електронна адреса')
                ->email()
                ->maxLength(255)
                ->live(onBlur: true)
                ->helperText(fn (\Filament\Schemas\Components\Utilities\Get $get): ?string => blank($get('email')) ? '⚠ '.self::EMAIL_HINT_NOTIFY.' '.self::EMAIL_HINT_MARKETING : null)
                ->columnSpan(1),

            TextInput::make('address')
                ->label('Адреса (старе, одне поле)')
                ->columnSpan(fn (): int => OrderResource::canSeeManagers() ? 2 : 3)
                // Old single-field address: view only, never saved from here (the structured fields below are the source).
                ->disabled()
                ->dehydrated(false)
                ->helperText('Старе поле зі старої бази — лише для перегляду. Адресу вносьте в поля нижче.'),

            // Old CRM «Автовизначення»: only for roles that oversee managers, read-only.
            TextInput::make('auto_address')
                ->label('Автовизначена адреса')
                ->visible(fn (): bool => OrderResource::canSeeManagers())
                ->disabled()
                ->dehydrated(false),

            TextInput::make('city')->label('Місто')->default('Київ')->maxLength(255),
            TextInput::make('street')->label('Вулиця')->maxLength(255),
            TextInput::make('house_number')->label('Будинок')->maxLength(20),
            TextInput::make('apartment_number')->label('Квартира')->maxLength(20),
            TextInput::make('floor')->label('Поверх')->maxLength(20),

            // Assigning a manager is only for roles that oversee managers (Адмін, Керівник
            // відділу продажу, Керівник компанії); a manager never sees this field.
            // Options = ACTIVE users with a manager role, shown as «Прізвище Ім'я».
            Select::make('manager_id')
                ->label('Менеджер')
                ->visible(fn (): bool => OrderResource::canSeeManagers())
                ->options(fn (): array => \App\Models\User::query()
                    ->where('is_active', true)
                    ->whereHas('roles', fn ($q) => $q->whereIn('name', config('panels.manager_roles', [])))
                    ->orderBy('name')
                    ->get()
                    ->mapWithKeys(fn (\App\Models\User $u): array => [
                        $u->id => implode(' ', array_slice(preg_split('/\s+/u', trim($u->name)), 0, 2)),
                    ])
                    ->all())
                ->searchable()
                ->placeholder('Не призначено'),

            Textarea::make('comment')
                ->label('Коментар')
                ->columnSpanFull(),
            ]),
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

    /** Tabs of the client card: «Контактні дані» (the form) + «Замовлення» + «Заявки». */
    public static function getRelations(): array
    {
        return [
            ClientResource\RelationManagers\OrdersRelationManager::class,
            ClientResource\RelationManagers\LeadsRelationManager::class,
        ];
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
