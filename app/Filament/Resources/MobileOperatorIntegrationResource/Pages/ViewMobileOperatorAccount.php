<?php

namespace App\Filament\Resources\MobileOperatorIntegrationResource\Pages;

use App\Filament\Resources\MobileOperatorIntegrationResource;
use App\Models\MobileOperatorAccount;
use App\Services\MobileOperators\KyivstarApiService;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Detail view for one operator connection — same approach as
 * ViewPrivatbankAccount: live API calls happen once in mount(), results
 * live in public Livewire properties; reload the page for fresh data.
 *
 * Shows: connection check (OAuth token grant) + current balance from
 * Kyivstar My Business B2B API billing accounts.
 */
class ViewMobileOperatorAccount extends ViewRecord
{
    protected static string $resource = MobileOperatorIntegrationResource::class;

    /** @var array{ok: bool, message: string}|null */
    public ?array $connection = null;

    /** @var array{amount: float, currency: string, accounts_count?: int}|null */
    public ?array $balance = null;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        /** @var MobileOperatorAccount $account */
        $account = $this->record;

        // Only Kyivstar has a driver for now; other operators show a
        // "driver not implemented" note instead of erroring.
        if ($account->operator === 'kyivstar') {
            try {
                $service = app(KyivstarApiService::class);

                $this->connection = $service->checkConnection($account);
                $this->balance = $service->getBalance($account);
            } catch (\Throwable) {
                $this->connection = ['ok' => false, 'message' => 'Помилка з\'єднання з API.'];
            }
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->label('Редагувати')
                ->modalHeading('Редагувати підключення')
                ->visible(fn (): bool => auth()->user()?->getActiveRoleName() !== 'Керівник компанії'),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components([

            Section::make('Дані підключення')
                ->columns(2)
                ->schema([
                    TextEntry::make('display_name')
                        ->label('Назва'),

                    TextEntry::make('operator')
                        ->label('Оператор')
                        ->formatStateUsing(fn (string $state): string => MobileOperatorAccount::operatorOptions()[$state] ?? $state),

                    TextEntry::make('phone_number')
                        ->label('Номер телефону')
                        ->copyable(),

                    TextEntry::make('user.name')
                        ->label('Користувач'),

                    TextEntry::make('client_id')
                        ->label('Client ID')
                        ->visible(fn (): bool => auth()->user()?->getActiveRoleName() !== 'Керівник компанії'),

                    IconEntry::make('is_active')
                        ->label('Активний')
                        ->boolean(),
                ]),

            Section::make('Стан підключення і баланс')
                ->schema([
                    TextEntry::make('_connection')
                        ->label('З\'єднання з API')
                        ->state(fn (): string => $this->connection['message']
                            ?? 'Для цього оператора драйвер API ще не реалізовано.')
                        ->badge()
                        ->color(fn (): string => match (true) {
                            $this->connection === null => 'gray',
                            $this->connection['ok'] => 'success',
                            default => 'danger',
                        }),

                    TextEntry::make('_balance')
                        ->label('Баланс рахунку')
                        ->state(function (): string {
                            if ($this->balance !== null) {
                                return number_format($this->balance['amount'], 2, '.', ' ')
                                    .' '.$this->balance['currency'];
                            }

                            return 'Не вдалося отримати баланс. Перевірте доступи API або права до розділу billing accounts у кабінеті Київстар.';
                        }),
                ]),
        ]);
    }
}
