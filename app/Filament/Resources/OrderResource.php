<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages\EditOrder;
use App\Filament\Resources\OrderResource\Pages\ListOrders;
use App\Models\Order;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * "Замовлення" — what a Lead becomes once a client commits. Deliberately
 * has NO create page/action here: an Order is only ever created from the
 * "Створити замовлення" action on the Lead edit page (see EditLead), so
 * it always starts from a real client + object address instead of being
 * typed in blind. If a genuine "walk-in order with no prior Lead" case
 * shows up later, add a CreateAction back in ListOrders rather than
 * resurrecting a generic blank form here.
 *
 * Like Lead, editing happens on a dedicated page (EditOrder), not a
 * modal — there's simply too much data (pricing, multiple installation
 * dates, crew, ...) for a modal to make sense. See EditOrder for the
 * field grouping and what was deliberately left out of this first pass.
 */
class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';

    protected static string|\UnitEnum|null $navigationGroup = 'Замовлення';

    protected static ?string $navigationLabel = 'Замовлення';

    protected static ?string $modelLabel = 'замовлення';

    protected static ?string $pluralModelLabel = 'замовлення';

    protected static ?int $navigationSort = 1;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('client.full_name')
                    ->label('Клієнт')
                    ->description(fn (Order $record): ?string => $record->client?->phone)
                    ->searchable(['client.last_name', 'client.first_name']),

                TextColumn::make('address')
                    ->label('Адреса')
                    ->limit(40)
                    ->searchable(),

                TextColumn::make('order_type')
                    ->label('Тип')
                    ->formatStateUsing(fn (?string $state): string => Order::orderTypeOptions()[$state] ?? '—'),

                TextColumn::make('manager.name')
                    ->label('Менеджер'),

                TextColumn::make('stage')
                    ->label('Етап')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => Order::stageOptions()[$state] ?? '—'),

                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'done' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => Order::statusOptions()[$state] ?? '—'),

                TextColumn::make('total_price')
                    ->label('Сума')
                    ->numeric()
                    ->formatStateUsing(fn (?int $state): string => $state ? number_format($state, 0, '', ' ').' грн' : '—'),

                TextColumn::make('created_at')
                    ->label('Дата створення')
                    ->dateTime('d.m.Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('stage')
                    ->label('Етап')
                    ->options(Order::stageOptions()),

                SelectFilter::make('status')
                    ->label('Статус')
                    ->options(Order::statusOptions()),

                SelectFilter::make('order_type')
                    ->label('Тип')
                    ->options(Order::orderTypeOptions()),
            ])
            ->recordActions([
                EditAction::make('edit'),
            ])
            // Rows must be clickable for VIEW-only roles too (Керівник
            // компанії): EditAction hides itself without Update:Order,
            // but the page itself now accepts the view permission and
            // renders read-only — see EditOrder::authorizeAccess().
            ->recordUrl(fn (Order $record): string => EditOrder::getUrl(['record' => $record]))
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'edit' => EditOrder::route('/{record}/edit'),
        ];
    }
}
