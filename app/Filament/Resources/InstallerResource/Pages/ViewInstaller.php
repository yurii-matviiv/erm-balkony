<?php

namespace App\Filament\Resources\InstallerResource\Pages;

use App\Filament\Resources\InstallerResource;
use Filament\Resources\Pages\ViewRecord;

class ViewInstaller extends ViewRecord
{
    protected static string $resource = InstallerResource::class;

    /** One row of tabs: «Контактні дані» | «Замовлення». */
    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return 'Картка монтажника';
    }

    public function getContentTabLabel(): ?string
    {
        return 'Контактні дані';
    }

    public function getContentTabIcon(): ?string
    {
        return 'heroicon-o-identification';
    }
}
