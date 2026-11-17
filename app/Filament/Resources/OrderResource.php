<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages\EditOrder;
use App\Filament\Resources\OrderResource\Pages\ListOrders;
use App\Models\Order;
use App\Models\User;
use App\Support\RolePanels;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * A user working in the MANAGER panel (see config/panels.php) sees only
     * their own orders — same as "Мої замовлення" in the old CRM (verified:
     * old `orders.manager_id` = the manager; Олена 720 there vs 719 here).
     * Applied in getEloquentQuery() so it covers the list, record lookup
     * (a manager cannot open someone else's order by URL) and search alike.
     * Admin-panel roles keep seeing everything.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user && RolePanels::idForUser($user) === RolePanels::MANAGER) {
            $query->where('manager_id', $user->id);
        }

        return $query;
    }

    /**
     * Roles that oversee ALL orders get the manager column and the manager filter
     * (same list as LeadResource::canSeeManagers()). A manager sees only their own
     * orders — see getEloquentQuery() — so for them both would be pointless.
     */
    public static function canSeeManagers(): bool
    {
        return in_array(
            auth()->user()?->getActiveRoleName(),
            ['Адмін', 'Керівник відділу продажу', 'Керівник компанії', 'super_admin'],
            true,
        );
    }

    public static function table(Table $table): Table
    {
        // Columns follow the old CRM's "Замовлення" list (order №, supplier
        // invoice №, address, client, montage date, installer, stage chip)
        // so managers recognise it; extras stay available in the column toggle.
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('Дог. № замовлення')
                    ->toggleable()
                    ->formatStateUsing(fn ($state): string => 'замовл. №'.$state)
                    ->sortable()
                    ->searchable(),

                TextColumn::make('invoice_from_supplier')
                    ->label('№ у постачальника')
                    ->toggleable()
                    ->wrap()
                    ->searchable(),

                TextColumn::make('address')
                    ->label('Адреса')
                    ->toggleable()
                    ->limit(60)
                    ->tooltip(fn (Order $record): ?string => $record->address)
                    ->searchable(),

                TextColumn::make('client.full_name')
                    ->label('Клієнт')
                    ->toggleable()
                    ->description(fn (Order $record): ?string => $record->client?->phone)
                    ->searchable(['client.last_name', 'client.first_name', 'client.phone']),

                TextColumn::make('montage_date')
                    ->label('Монтаж')
                    ->toggleable()
                    ->formatStateUsing(fn ($state): string => $state ? $state->locale('uk')->isoFormat('dd DD.MM') : '—')
                    ->sortable(),

                TextColumn::make('installer.name')
                    ->label('Монтажник')
                    ->toggleable(),

                TextColumn::make('order_type')
                    ->label('Тип')
                    ->formatStateUsing(fn (?string $state): string => Order::orderTypeOptions()[$state] ?? '—')
                    ->toggleable(isToggledHiddenByDefault: true),

                // Surname only (names are stored surname-first), like in the leads table.
                TextColumn::make('manager.name')
                    ->label('Менеджер')
                    ->toggleable()
                    ->formatStateUsing(fn (?string $state): string => $state ? (explode(' ', trim($state))[0] ?: $state) : '')
                    ->placeholder('—')
                    ->visible(fn (): bool => static::canSeeManagers()),

                TextColumn::make('stage')
                    ->label('Етап')
                    ->toggleable()
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'ready', 'success' => 'success',
                        'in_work' => 'info',
                        'in_process' => 'primary',
                        'montage' => 'danger',
                        'measuring' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => Order::stageOptions()[$state] ?? '—'),

                TextColumn::make('status')
                    ->label('Статус')
                    ->toggleable(isToggledHiddenByDefault: true)
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
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->formatStateUsing(fn (?int $state): string => $state ? number_format($state, 0, '', ' ').' грн' : '—'),

                // The page-level date bar filters by this date, so it is shown by default.
                TextColumn::make('created_at')
                    ->label('Дата створення')
                    ->dateTime('d.m.Y')
                    ->toggleable()
                    ->sortable(),
            ])
            ->filters([
                // Same manager multi-select as in the leads table: active managers
                // (+ "Не призначено"), only for roles that oversee all orders.
                SelectFilter::make('managers')
                    ->label('Менеджер')
                    ->multiple()
                    ->visible(fn (): bool => static::canSeeManagers())
                    ->options(function (): array {
                        $users = User::query()
                            ->where('is_active', true)
                            ->whereHas('roles', fn (Builder $q) => $q->whereIn('name', config('panels.manager_roles', [])))
                            ->orderBy('name')
                            ->get()
                            ->mapWithKeys(fn (User $u): array => [
                                $u->id => implode(' ', array_slice(preg_split('/\s+/u', trim($u->name)), 0, 2)),
                            ])
                            ->all();

                        return ['none' => 'Не призначено'] + $users;
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        $ids = array_filter((array) ($data['values'] ?? []), fn ($v): bool => filled($v));

                        if ($ids === []) {
                            return $query;
                        }

                        $withNone = in_array('none', $ids, true);
                        $real = array_values(array_diff($ids, ['none']));

                        return $query->where(function (Builder $q) use ($real, $withNone): void {
                            if ($real !== []) {
                                $q->whereIn('orders.manager_id', $real);
                            }

                            if ($withNone) {
                                $q->orWhereNull('orders.manager_id');
                            }
                        });
                    }),

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
            ->persistColumnsInSession()
            ->paginationPageOptions([20, 100])
            ->defaultPaginationPageOption(20)
            ->recordActions([
                EditAction::make('edit'),
            ])
            // Rows must be clickable for VIEW-only roles too (Керівник
            // компанії): EditAction hides itself without Update:Order,
            // but the page itself now accepts the view permission and
            // renders read-only — see EditOrder::authorizeAccess().
            ->recordUrl(fn (Order $record): string => EditOrder::getUrl(['record' => $record]))
            // Old CRM lists by montage date, newest first.
            ->defaultSort('montage_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'edit' => EditOrder::route('/{record}/edit'),
        ];
    }
}
