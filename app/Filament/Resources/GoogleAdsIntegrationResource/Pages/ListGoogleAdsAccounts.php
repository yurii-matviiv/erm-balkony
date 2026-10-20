<?php

namespace App\Filament\Resources\GoogleAdsIntegrationResource\Pages;

use App\Filament\Resources\GoogleAdsIntegrationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGoogleAdsAccounts extends ListRecords
{
    protected static string $resource = GoogleAdsIntegrationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Нове підключення')
                ->modalHeading('Нове підключення Google Ads'),
        ];
    }
}
