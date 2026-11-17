<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Concerns\HasDateRangeBar;
use App\Filament\Resources\OrderResource;
use App\Support\RolePanels;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

/**
 * No create action here on purpose — see OrderResource docblock. Orders
 * are only created via the "Створити замовлення" action on EditLead.
 */
class ListOrders extends ListRecords
{
    use HasDateRangeBar;

    protected static string $resource = OrderResource::class;

    /** Custom view only to put the standard date bar above the table. */
    protected string $view = 'filament.resources.order-resource.pages.list-orders';

    /** Page-level date bar filters by order creation date. */
    protected function getTableQuery(): Builder|\Illuminate\Database\Eloquent\Relations\Relation|null
    {
        return $this->applyDateRange(parent::getTableQuery(), 'orders.created_at');
    }

    // Same heading as the old CRM: "Мої замовлення: 720".
    public function getHeading(): string|Htmlable
    {
        if (RolePanels::idForUser(auth()->user()) === RolePanels::MANAGER) {
            return 'Мої замовлення: '.number_format(OrderResource::getEloquentQuery()->count(), 0, '', ' ');
        }

        return parent::getHeading();
    }
}
