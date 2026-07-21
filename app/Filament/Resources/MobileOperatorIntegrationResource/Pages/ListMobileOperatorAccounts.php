<?php

namespace App\Filament\Resources\MobileOperatorIntegrationResource\Pages;

use App\Filament\Resources\MobileOperatorIntegrationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMobileOperatorAccounts extends ListRecords
{
    protected static string $resource = MobileOperatorIntegrationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Додати підключення')
                ->modalHeading('Нове підключення оператора')
                ->visible(fn (): bool => auth()->user()?->getActiveRoleName() !== 'Керівник компанії'),
        ];
    }
}
