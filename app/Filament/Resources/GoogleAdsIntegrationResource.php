<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GoogleAdsIntegrationResource\Pages\ListGoogleAdsAccounts;
use App\Filament\Resources\GoogleAdsIntegrationResource\Pages\ViewGoogleAdsAccount;
use App\Models\GoogleAdsAccount;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Google Ads API gateway connection.
 *
 * Mirrors the existing integration pattern (PrivatBank, Mobile operators,
 * Binotel): List + View routes, Create/Edit as modals, navigation group
 * "Інтеграції", default-deny permissions via Filament Shield. Read-only
 * connection check for now — no conversion upload / outbox logic here yet.
 */
class GoogleAdsIntegrationResource extends Resource
{
    protected static ?string $model = GoogleAdsAccount::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationLabel = 'Google Ads API';

    protected static string|\UnitEnum|null $navigationGroup = 'Інтеграції';

    protected static ?int $navigationSort = 13;

    protected static ?string $modelLabel = 'підключення Google Ads';

    protected static ?string $pluralModelLabel = 'підключення Google Ads';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('display_name')
                ->label('Назва')
                ->placeholder('Google Ads — Vikna&Balkony')
                ->required()
                ->maxLength(255),

            TextInput::make('customer_id')
                ->label('Customer ID')
                ->placeholder('772-311-4436')
                ->required()
                ->maxLength(255)
                ->helperText('ID акаунту Google Ads. Дефіси можна лишити — вони прибираються автоматично.'),

            Textarea::make('service_account_json')
                ->label('JSON-ключ service account')
                ->required()
                ->rows(8)
                ->helperText('Повний вміст файлу ключа з Google Cloud (IAM → Service Accounts → Keys). Зберігається в зашифрованому вигляді.')
                ->rule(function () {
                    return function (string $attribute, $value, \Closure $fail) {
                        $decoded = json_decode((string) $value, true);
                        if (! is_array($decoded) || ! isset($decoded['private_key'], $decoded['client_email'])) {
                            $fail('Це не схоже на валідний JSON-ключ service account (очікуються поля private_key і client_email).');
                        }
                    };
                }),

            Toggle::make('is_active')
                ->label('Активний')
                ->default(true)
                ->inline(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('display_name')
                    ->label('Назва')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('customer_id')
                    ->label('Customer ID')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('service_account_email')
                    ->label('Service account')
                    ->placeholder('—')
                    ->toggleable(),

                IconColumn::make('is_active')
                    ->label('Активний')
                    ->boolean(),

                TextColumn::make('updated_at')
                    ->label('Оновлено')
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordUrl(fn (GoogleAdsAccount $record): string => static::getUrl('view', ['record' => $record]))
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn (): bool => auth()->user()?->getActiveRoleName() !== 'Керівник компанії'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGoogleAdsAccounts::route('/'),
            'view' => ViewGoogleAdsAccount::route('/{record}'),
        ];
    }
}
