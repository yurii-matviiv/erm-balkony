<?php

namespace App\Filament\Resources\ClientResource\Pages;

use App\Filament\Resources\ClientResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditClient extends EditRecord
{
    protected static string $resource = ClientResource::class;

    /** One row of tabs: «Контактні дані» | «Замовлення» | «Заявки». */
    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return 'Картка клієнта';
    }

    public function getContentTabLabel(): ?string
    {
        return 'Контактні дані';
    }

    public function getContentTabIcon(): ?string
    {
        return 'heroicon-o-identification';
    }

    /** Після збереження повертаємось у картку (перегляд). */
    protected function getRedirectUrl(): ?string
    {
        return ClientResource::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\ViewAction::make()->label('До картки')->icon('heroicon-o-arrow-left'),
            DeleteAction::make(),
        ];
    }
}
