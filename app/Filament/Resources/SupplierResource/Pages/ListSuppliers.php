<?php

namespace App\Filament\Resources\SupplierResource\Pages;

use App\Filament\Resources\SupplierResource;
use App\Models\Supplier;
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
                ->modalWidth('2xl')
                // Runs AFTER the supplier (and its contacts/payment
                // profiles) are already saved in the new system. Shared
                // with EditAction::after() on SupplierResource::table() —
                // see SupplierResource::pushToLegacyIfRequested() and the
                // toggle's docblock in SupplierResource::form().
                ->after(fn (Supplier $record, array $data) => SupplierResource::pushToLegacyIfRequested($record, $data)),
        ];
    }
}
