<?php

namespace App\Filament\Resources\SupplierResource\Pages;

use App\Filament\Resources\SupplierResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSuppliers extends ListRecords
{
    protected static string $resource = SupplierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No 'create' page is registered on the resource, so this
            // automatically opens as a modal instead of navigating away.
            CreateAction::make()
                ->modalWidth('2xl'),
        ];
    }
}
