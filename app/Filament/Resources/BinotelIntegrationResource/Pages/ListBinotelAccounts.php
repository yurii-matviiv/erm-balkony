<?php

namespace App\Filament\Resources\BinotelIntegrationResource\Pages;

use App\Filament\Resources\BinotelIntegrationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBinotelAccounts extends ListRecords
{
    protected static string $resource = BinotelIntegrationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Нове підключення')
                ->modalHeading('Нове підключення Binotel'),
        ];
    }
}
