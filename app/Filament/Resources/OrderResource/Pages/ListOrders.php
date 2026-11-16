<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Support\RolePanels;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

/**
 * No create action here on purpose — see OrderResource docblock. Orders
 * are only created via the "Створити замовлення" action on EditLead.
 */
class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    // Same heading as the old CRM: "Мої замовлення: 720".
    public function getHeading(): string|Htmlable
    {
        if (RolePanels::idForUser(auth()->user()) === RolePanels::MANAGER) {
            return 'Мої замовлення: '.number_format(OrderResource::getEloquentQuery()->count(), 0, '', ' ');
        }

        return parent::getHeading();
    }
}
