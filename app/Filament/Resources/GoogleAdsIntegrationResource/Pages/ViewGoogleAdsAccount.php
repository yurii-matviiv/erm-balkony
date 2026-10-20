<?php

namespace App\Filament\Resources\GoogleAdsIntegrationResource\Pages;

use App\Filament\Resources\GoogleAdsIntegrationResource;
use App\Services\GoogleAds\GoogleAdsApiService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Detail view for one Google Ads gateway connection.
 *
 * Live API check happens once on page load, and again on demand via the
 * "Перевірити з'єднання" header action — a read-only GAQL smoke test, no
 * conversions/outbox logic here yet.
 */
class ViewGoogleAdsAccount extends ViewRecord
{
    protected static string $resource = GoogleAdsIntegrationResource::class;

    /** @var array{ok: bool, message: string, customer_id?: string, descriptive_name?: string, raw?: mixed}|null */
    public ?array $connection = null;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->connection = app(GoogleAdsApiService::class)->checkConnection($this->record);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('recheckConnection')
                ->label('Перевірити з\'єднання')
                ->icon('heroicon-o-arrow-path')
                ->action(function (): void {
                    $this->connection = app(GoogleAdsApiService::class)->checkConnection($this->record);

                    Notification::make()
                        ->title($this->connection['ok'] ? 'З\'єднання працює' : 'З\'єднання не працює')
                        ->body($this->connection['message'])
                        ->color($this->connection['ok'] ? 'success' : 'danger')
                        ->send();
                }),

            EditAction::make()
                ->label('Редагувати')
                ->modalHeading('Редагувати підключення Google Ads')
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

                    TextEntry::make('customer_id')
                        ->label('Customer ID')
                        ->copyable(),

                    TextEntry::make('service_account_email')
                        ->label('Service account')
                        ->placeholder('—')
                        ->copyable()
                        ->visible(fn (): bool => auth()->user()?->getActiveRoleName() !== 'Керівник компанії'),

                    IconEntry::make('is_active')
                        ->label('Активний')
                        ->boolean(),
                ]),

            Section::make('Стан підключення')
                ->schema([
                    TextEntry::make('_connection')
                        ->label('З\'єднання з API')
                        ->state(fn (): string => $this->connection['message'] ?? 'Перевірка ще не виконана.')
                        ->badge()
                        ->color(fn (): string => match (true) {
                            $this->connection === null => 'gray',
                            $this->connection['ok'] => 'success',
                            default => 'danger',
                        }),

                    TextEntry::make('_descriptive_name')
                        ->label('Назва акаунту в Google Ads')
                        ->state(fn (): string => $this->connection['descriptive_name'] ?? '—'),

                    TextEntry::make('_raw')
                        ->label('Сира відповідь API (при помилці)')
                        ->state(fn (): string => isset($this->connection['raw'])
                            ? json_encode($this->connection['raw'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                            : '—')
                        ->visible(fn (): bool => isset($this->connection['raw']) && auth()->user()?->getActiveRoleName() !== 'Керівник компанії')
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
