<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PrivatbankIntegrationResource\Pages\ListPrivatbankAccounts;
use App\Filament\Resources\PrivatbankIntegrationResource\Pages\ViewPrivatbankAccount;
use App\Models\PrivatbankAccount;
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
 * Manages PrivatBank Business API accounts — one per FOP/manager.
 *
 * Follows the "modal resource" pattern (same as SupplierResource):
 * no dedicated Create/Edit pages, those open as modals. Only the List
 * page and the View (detail) page are registered as real routes.
 *
 * The View page (ViewPrivatbankAccount) shows live data from the
 * PrivatBank API: current balance + last 10 transactions.
 *
 * Navigation group: "Інтеграції"
 */
class PrivatbankIntegrationResource extends Resource
{
    protected static ?string $model = PrivatbankAccount::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'ПриватБанк';

    protected static string|\UnitEnum|null $navigationGroup = 'Інтеграції';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'акаунт ПриватБанк';

    protected static ?string $pluralModelLabel = 'акаунти ПриватБанк';

    // ──────────────────────────────────────────────
    // Form (used in Create/Edit modals)
    // ──────────────────────────────────────────────

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('user_id')
                ->label('Менеджер / ФОП')
                ->relationship('user', 'name')
                ->options(fn () => User::orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->required(),

            TextInput::make('display_name')
                ->label('Назва (відображається в інтерфейсі)')
                ->placeholder('ФОП Матвієнко Юрій')
                ->required()
                ->maxLength(255),

            TextInput::make('edrpou')
                ->label('ЄДРПОУ')
                ->placeholder('3012345678')
                ->maxLength(20),

            TextInput::make('account_number')
                ->label('IBAN')
                ->placeholder('UA673052990000026001006804387')
                ->required()
                ->maxLength(34),

            TextInput::make('token')
                ->label('API-токен ПриватБанку')
                ->password()
                ->revealable()
                ->required()
                ->maxLength(500)
                ->helperText('Токен зберігається в зашифрованому вигляді.'),

            TextInput::make('user_agent')
                ->label('User-Agent (для API)')
                ->placeholder('API_uploading_invoices_Iryna')
                ->maxLength(100)
                ->helperText('Повинен збігатися з тим, що зареєстровано в ПриватБанку.'),

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

                TextColumn::make('user.name')
                    ->label('Менеджер')
                    ->sortable(),

                TextColumn::make('edrpou')
                    ->label('ЄДРПОУ')
                    ->toggleable(),

                TextColumn::make('account_number')
                    ->label('IBAN')
                    ->formatStateUsing(fn (string $state): string =>
                        substr($state, 0, 4) . ' ... ' . substr($state, -4)
                    )
                    ->tooltip(fn (PrivatbankAccount $record): string => $record->account_number),

                IconColumn::make('is_active')
                    ->label('Активний')
                    ->boolean(),

                TextColumn::make('updated_at')
                    ->label('Оновлено')
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordUrl(fn (PrivatbankAccount $record): string =>
                static::getUrl('view', ['record' => $record])
            )
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn (): bool => auth()->user()?->getActiveRoleName() !== 'Керівник компанії'),
            ]);
    }

    // ──────────────────────────────────────────────
    // Pages
    // ──────────────────────────────────────────────

    public static function getPages(): array
    {
        return [
            'index' => ListPrivatbankAccounts::route('/'),
            'view'  => ViewPrivatbankAccount::route('/{record}'),
            // No dedicated create/edit routes — those use modals
        ];
    }
}
