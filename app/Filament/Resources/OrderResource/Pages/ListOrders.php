<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use Filament\Resources\Pages\ListRecords;

/**
 * No create action here on purpose — see OrderResource docblock. Orders
 * are only created via the "Створити замовлення" action on EditLead.
 */
class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;
}
