<?php

namespace App\Filament\Resources\BinotelIntegrationResource\Pages;

use App\Filament\Resources\BinotelIntegrationResource;
use App\Services\Binotel\BinotelApiService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Detail view for one Binotel gateway connection.
 *
 * Live API check happens once on page load. This page does not import calls;
 * it only proves that the saved credentials can be reused by future modules.
 */
class ViewBinotelAccount extends ViewRecord
{
    protected static string $resource = BinotelIntegrationResource::class;

    /** @var array{ok: bool, message: string, employees_count?: int}|null */
    public ?array $connection = null;

    /** @var array{ok: bool, message: string, response_text?: string, raw?: array}|null */
    public ?array $ussdResult = null;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->connection = app(BinotelApiService::class)->checkConnection($this->record);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('testUssd')
                ->label('Перевірити USSD')
                ->icon('heroicon-o-signal')
                ->modalHeading('Тест USSD через Binotel')
                ->modalDescription('Експериментальний запит до GSM-номера через Binotel. Результат не зберігається в базі.')
                ->modalSubmitActionLabel('Надіслати')
                ->form([
                    TextInput::make('number')
                        ->label('Номер телефону')
                        ->placeholder('0933619359')
                        ->required()
                        ->maxLength(20),

                    TextInput::make('code')
                        ->label('USSD-код')
                        ->default('*111#')
                        ->required()
                        ->maxLength(30),
                ])
                ->action(function (array $data): void {
                    $this->ussdResult = app(BinotelApiService::class)->sendUssd(
                        $this->record,
                        $data['number'],
                        $data['code'],
                    );

                    Notification::make()
                        ->title($this->ussdResult['ok'] ? 'USSD-запит виконано' : 'USSD-запит не виконано')
                        ->body($this->ussdResult['response_text'] ?? $this->ussdResult['message'])
                        ->color($this->ussdResult['ok'] ? 'success' : 'danger')
                        ->send();
                }),

            EditAction::make()
                ->label('Редагувати')
                ->modalHeading('Редагувати підключення Binotel')
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

                    TextEntry::make('company_name')
                        ->label('Компанія')
                        ->placeholder('—'),

                    TextEntry::make('company_id')
                        ->label('Company ID')
                        ->copyable(),

                    TextEntry::make('api_key')
                        ->label('API Key')
                        ->copyable()
                        ->visible(fn (): bool => auth()->user()?->getActiveRoleName() !== 'Керівник компанії'),

                    IconEntry::make('is_active')
                        ->label('Активний')
                        ->boolean(),
                ]),

            Section::make('Стан підключення')
                ->schema([
                    TextEntry::make('_connection')
                        ->label('Зʼєднання з API')
                        ->state(fn (): string => $this->connection['message'] ?? 'Перевірка ще не виконана.')
                        ->badge()
                        ->color(fn (): string => match (true) {
                            $this->connection === null => 'gray',
                            $this->connection['ok'] => 'success',
                            default => 'danger',
                        }),

                    TextEntry::make('_employees_count')
                        ->label('Працівників у Binotel')
                        ->state(fn (): string => isset($this->connection['employees_count'])
                            ? (string) $this->connection['employees_count']
                            : '—'),
                ]),

            Section::make('Тест USSD')
                ->schema([
                    TextEntry::make('_ussd_result')
                        ->label('Остання відповідь')
                        ->state(function (): string {
                            if ($this->ussdResult === null) {
                                return 'Ще не виконувалось.';
                            }

                            if (! empty($this->ussdResult['response_text'])) {
                                return $this->ussdResult['response_text'];
                            }

                            return $this->ussdResult['message'];
                        })
                        ->columnSpanFull(),

                    TextEntry::make('_ussd_raw')
                        ->label('Сира відповідь API')
                        ->state(fn (): string => $this->ussdResult !== null
                            ? json_encode($this->ussdResult['raw'] ?? [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                            : '—')
                        ->visible(fn (): bool => $this->ussdResult !== null && auth()->user()?->getActiveRoleName() !== 'Керівник компанії')
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
