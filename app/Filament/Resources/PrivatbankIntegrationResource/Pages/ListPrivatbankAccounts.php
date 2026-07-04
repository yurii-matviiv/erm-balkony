<?php

namespace App\Filament\Resources\PrivatbankIntegrationResource\Pages;

use App\Filament\Resources\PrivatbankIntegrationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPrivatbankAccounts extends ListRecords
{
    protected static string $resource = PrivatbankIntegrationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Додати акаунт')
                ->modalHeading('Новий акаунт ПриватБанку')
                ->visible(fn (): bool => auth()->user()?->getActiveRoleName() !== 'Керівник компанії'),
        ];
    }
}
