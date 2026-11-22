<?php

namespace App\Filament\Resources\ClientResource\Pages;

use App\Filament\Resources\ClientResource;
use Filament\Resources\Pages\ViewRecord;

/**
 * Картка клієнта (перегляд) — за зразком картки користувача: шапка з олівцем
 * і рядки з іконками. Редагування — на окремій сторінці (EditClient), куди веде олівець.
 * Вкладки «Контактні дані» | «Замовлення» | «Заявки» — в один ряд, як і раніше.
 */
class ViewClient extends ViewRecord
{
    protected static string $resource = ClientResource::class;

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
}
