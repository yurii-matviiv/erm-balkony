<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MobileOperatorIntegrationResource\Pages\ListMobileOperatorAccounts;
use App\Filament\Resources\MobileOperatorIntegrationResource\Pages\ViewMobileOperatorAccount;
use App\Models\MobileOperatorAccount;
use App\Models\User;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Mobile-operator API connections — mirrors PrivatbankIntegrationResource
 * (same "modal resource" pattern: List + View routes, Create/Edit as
 * modals; same navigation group "Інтеграції"; same access rules —
 * default-deny, admin enables per role via "Бокова панель").
 *
 * Operator + phone number are both explicit so it is always clear where
 * the pulled data belongs (explicit user requirement).
 */
class MobileOperatorIntegrationResource extends Resource
{
    protected static ?string $model = MobileOperatorAccount::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-device-phone-mobile';

    protected static ?string $navigationLabel = 'Мобільні оператори';

    protected static string|\UnitEnum|null $navigationGroup = 'Інтеграції';

    protected static ?int $navigationSort = 11;

    protected static ?string $modelLabel = 'акаунт оператора';

    protected static ?string $pluralModelLabel = 'акаунти операторів';

    // ──────────────────────────────────────────────
    // Form (Create/Edit modals)
    // ──────────────────────────────────────────────

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('operator')
                ->label('Оператор')
                ->options(MobileOperatorAccount::operatorOptions())
                ->default('kyivstar')
                ->required(),

            TextInput::make('phone_number')
                ->label('Номер телефону')
                ->placeholder('380670000000')
                ->required()
                ->maxLength(20)
                ->helperText('Номер, якому належать дані цього підключення.'),

            TextInput::make('display_name')
                ->label('Назва (відображається в інтерфейсі)')
                ->placeholder('Київстар — корпоративний Юрій')
                ->required()
                ->maxLength(255),

            Select::make('user_id')
                ->label('Користувач')
                ->relationship('user', 'name')
                ->options(fn () => User::orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->required(),

            TextInput::make('client_id')
                ->label('Client ID (API-портал)')
                ->required()
                ->maxLength(255),

            TextInput::make('client_secret')
                ->label('Client Secret (API-портал)')
                ->password()
                ->revealable()
                ->required()
                ->maxLength(500)
                ->helperText('Зберігається в зашифрованому вигляді.'),

            Toggle::make('is_active')
                ->label('Активний')
                ->default(true)
                ->inline(false),
        ]);
    }

    // ──────────────────────────────────────────────
    // Table (List page)
    // ──────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('display_name')
                    ->label('Назва')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('operator')
                    ->label('Оператор')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => MobileOperatorAccount::operatorOptions()[$state] ?? $state)
                    ->color('info'),

                TextColumn::make('phone_number')
                    ->label('Номер')
                    ->searchable(),

                TextColumn::make('user.name')
                    ->label('Користувач')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Активний')
                    ->boolean(),

                TextColumn::make('updated_at')
                    ->label('Оновлено')
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordUrl(fn (MobileOperatorAccount $record): string => static::getUrl('view', ['record' => $record]))
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn (): bool => auth()->user()?->getActiveRoleName() !== 'Керівник компанії'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMobileOperatorAccounts::route('/'),
            'view' => ViewMobileOperatorAccount::route('/{record}'),
            // No dedicated create/edit routes — those use modals
        ];
    }
}
