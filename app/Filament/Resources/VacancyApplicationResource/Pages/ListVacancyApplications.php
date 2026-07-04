<?php

namespace App\Filament\Resources\VacancyApplicationResource\Pages;

use App\Filament\Resources\VacancyApplicationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVacancyApplications extends ListRecords
{
    protected static string $resource = VacancyApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Додати заявку')
                ->modalHeading('Нова заявка на вакансію')
                ->modalWidth('xl'),
        ];
    }
}
