<?php

namespace App\Filament\Resources\BinotelIntegrationResource\Pages;

use App\Filament\Resources\BinotelIntegrationResource;
use App\Services\Binotel\BinotelApiService;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
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

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->connection = app(BinotelApiService::class)->checkConnection($this->record);
    }

    protected function getHeaderActions(): array
    {
        return [
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
        ]);
    }
}
