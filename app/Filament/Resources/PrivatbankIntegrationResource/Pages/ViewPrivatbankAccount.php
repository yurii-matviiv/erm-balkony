<?php

namespace App\Filament\Resources\PrivatbankIntegrationResource\Pages;

use App\Filament\Resources\PrivatbankIntegrationResource;
use App\Services\Privatbank\PrivatbankApiService;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

/**
 * Detail view for a single PrivatBank account.
 *
 * On mount, calls the PrivatBank API (getBalance + getTransactions) and
 * stores results as public Livewire properties. The live-data Section
 * renders those properties into a Blade partial via TextEntry->state()
 * returning HtmlString.
 *
 * Calls happen once on page load — no auto-refresh. The user can reload
 * the page to get fresh data.
 */
class ViewPrivatbankAccount extends ViewRecord
{
    protected static string $resource = PrivatbankIntegrationResource::class;

    // Loaded from the API in mount() — stored as public properties so
    // Livewire can keep them across re-renders without extra API calls.
    public ?array $apiBalance = null;

    // Period (30-day) income/expense totals — computed from actual transactions,
    // not from the /balance endpoint (which doesn't aggregate multi-day turnovers).
    public array $apiPeriodStats = ['income' => 0.0, 'expense' => 0.0, 'currency' => 'UAH'];

    // First 10 transactions to display in the table.
    public array $apiTransactions = [];
    public bool $apiError = false;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        try {
            $service = app(PrivatbankApiService::class);

            // Today's balance (turnoverCredit/Debt = today only, from /balance endpoint)
            $this->apiBalance = $service->getBalance($this->record);

            // Fetch up to 500 transactions for the last 30 days.
            // We need all of them to compute accurate income/expense totals —
            // the /balance API does not aggregate turnovers over multi-day periods.
            $allTransactions = $service->getTransactions(
                $this->record,
                now()->subDays(30)->format('d-m-Y'),
                now()->format('d-m-Y'),
                500,
            );

            // Sum income (C) and expense (D) from the raw transaction list.
            $income  = 0.0;
            $expense = 0.0;
            foreach ($allTransactions as $tx) {
                $amount = (float) ($tx['SUM'] ?? 0);
                if (($tx['TRANTYPE'] ?? '') === 'C') {
                    $income += $amount;
                } else {
                    $expense += $amount;
                }
            }

            $this->apiPeriodStats = [
                'income'   => $income,
                'expense'  => $expense,
                'currency' => $allTransactions[0]['CCY'] ?? ($this->apiBalance['CCY'] ?? 'UAH'),
            ];

            // Display only the 10 most recent transactions in the table.
            $this->apiTransactions = array_slice($allTransactions, 0, 10);

        } catch (\Throwable $e) {
            $this->apiError = true;
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->label('Редагувати')
                ->modalHeading('Редагувати акаунт ПриватБанку')
                ->visible(fn (): bool => auth()->user()?->getActiveRoleName() !== 'Керівник компанії'),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components([

            Section::make('Дані акаунту')
                ->columns(2)
                ->schema([
                    TextEntry::make('display_name')
                        ->label('Назва'),

                    TextEntry::make('user.name')
                        ->label('Менеджер / ФОП'),

                    TextEntry::make('edrpou')
                        ->label('ЄДРПОУ')
                        ->placeholder('—'),

                    TextEntry::make('account_number')
                        ->label('IBAN')
                        ->copyable(),

                    TextEntry::make('user_agent')
                        ->label('User-Agent')
                        ->placeholder('—')
                        ->visible(fn (): bool => auth()->user()?->getActiveRoleName() !== 'Керівник компанії'),

                    IconEntry::make('is_active')
                        ->label('Активний')
                        ->boolean(),
                ]),

            Section::make('Баланс і транзакції')
                ->schema([
                    TextEntry::make('_api_live_data')
                        ->label('')
                        ->state(function (): HtmlString {
                            return new HtmlString(
                                view(
                                    'filament.resources.privatbank-integration-resource.pages.account-live-data',
                                    [
                                        'balance'      => $this->apiBalance,
                                        'periodStats'  => $this->apiPeriodStats,
                                        'transactions' => $this->apiTransactions,
                                        'account'      => $this->record,
                                        'apiError'     => $this->apiError,
                                    ]
                                )->render()
                            );
                        })
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
