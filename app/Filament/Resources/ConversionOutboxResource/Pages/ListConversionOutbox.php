<?php

namespace App\Filament\Resources\ConversionOutboxResource\Pages;

use App\Filament\Resources\ConversionOutboxResource;
use App\Models\ConversionOutbox;
use App\Services\Conversions\ConversionSettings;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Artisan;

class ListConversionOutbox extends ListRecords
{
    protected static string $resource = ConversionOutboxResource::class;

    protected function getHeaderActions(): array
    {
        $modeLabels = ['off' => 'ВИМКНЕНО', 'validate' => 'ПЕРЕВІРКА (не надсилає)', 'live' => 'БОЙОВИЙ'];

        return [
            Action::make('settings')
                ->label(fn (): string => 'Режим: '.($modeLabels[ConversionSettings::mode()] ?? ConversionSettings::mode()))
                ->icon('heroicon-o-cog-6-tooth')
                ->color(fn (): string => match (ConversionSettings::mode()) {
                    'live' => 'danger',
                    'validate' => 'warning',
                    default => 'gray',
                })
                ->fillForm(fn (): array => [
                    'mode' => ConversionSettings::mode(),
                    'buffer' => ConversionSettings::bufferMinutes(),
                ])
                ->schema([
                    Select::make('mode')
                        ->label('Режим')
                        ->options([
                            'off' => 'Вимкнено: нічого не відбувається',
                            'validate' => 'Перевірка: збирається черга, Google лише перевіряє (нічого не надсилається)',
                            'live' => 'Бойовий: конверсії реально надсилаються в Google Ads',
                        ])
                        ->required()
                        ->helperText('Бойовий режим доступний після одноразового baseline (команда conversions:baseline).'),
                    TextInput::make('buffer')
                        ->label('Буфер перед відправкою, хвилин')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(1440)
                        ->required(),
                ])
                ->modalHeading('Налаштування конверсій Google Ads')
                ->action(function (array $data): void {
                    if ($data['mode'] === 'live' && ConversionSettings::baselineAt() === null) {
                        Notification::make()->title('Спершу потрібен baseline')->body('Виконайте: php artisan conversions:baseline')->danger()->send();

                        return;
                    }

                    ConversionSettings::setMode($data['mode']);
                    ConversionSettings::setBufferMinutes((int) $data['buffer']);

                    Notification::make()->title('Збережено')->success()->send();
                }),

            Action::make('run_now')
                ->label('Запустити зараз')
                ->icon('heroicon-o-play')
                ->requiresConfirmation()
                ->modalDescription('Оновить чергу і відправить усе, що вже пройшло буфер, згідно з поточним режимом.')
                ->action(function (): void {
                    Artisan::call('conversions:run');

                    Notification::make()->title('Виконано')->body(trim(Artisan::output()) ?: 'Режим вимкнено — нічого не зроблено.')->success()->send();
                }),
        ];
    }
}
