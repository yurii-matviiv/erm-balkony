<?php

namespace App\Filament\Resources\InstallerResource\RelationManagers;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use App\Services\Salary\SalaryService;
use App\Support\RolePanels;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * «Замовлення» tab of the installer card: orders this installer works on.
 * Read-only; a row opens the order. A manager sees only his own orders.
 */
class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'installerOrders';

    protected static ?string $title = 'Замовлення';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-shopping-bag';

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $count = static::scopeToUser($ownerRecord->installerOrders())->count();

        return $count > 0 ? (string) $count : null;
    }

    private static function scopeToUser($query)
    {
        $user = auth()->user();

        if ($user && RolePanels::idForUser($user) === RolePanels::MANAGER) {
            $query->where('orders.manager_id', $user->id);
        }

        return $query;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => static::scopeToUser(SalaryService::withPaymentSums($query->with(['client', 'manager']))))
            ->recordUrl(fn (Order $record): string => OrderResource::getUrl('edit', ['record' => $record]))
            ->columns([
                TextColumn::make('id')->label('Замовлення №')->wrapHeader()->sortable(),
                TextColumn::make('contract_number')->label('Договір №')->wrapHeader()->placeholder('—'),
                TextColumn::make('stage')
                    ->label('Готовність')
                    ->filledBadge(fn (?string $state): string => match ($state) {
                        'ready', 'success' => 'success',
                        'in_work' => 'info',
                        'in_process' => 'primary',
                        'montage' => 'danger',
                        'measuring' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => Order::stageOptions()[$state] ?? '—'),
                TextColumn::make('montage_date')
                    ->label('Монтаж')
                    ->formatStateUsing(fn ($state): string => $state ? $state->locale('uk')->isoFormat('dd DD.MM.YYYY') : '—')
                    ->sortable(),
                TextColumn::make('client.full_name')->label('Клієнт')->wrapHeader()->placeholder('—'),
                TextColumn::make('address')
                    ->label('Адреса монтажу')
                    ->wrap()
                    ->extraCellAttributes(['style' => 'max-width: 18rem; white-space: normal;']),
                TextColumn::make('total_price')
                    ->label('Сума, грн')
                    ->wrapHeader()
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2, ',', ' ')),
                TextColumn::make('client_paid')
                    ->label('Отримано, грн')
                    ->wrapHeader()
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2, ',', ' ')),
                TextColumn::make('manager.name')
                    ->label('Менеджер')
                    ->placeholder('—')
                    ->formatStateUsing(fn (?string $state): string => $state ? (explode(' ', trim($state))[0] ?: $state) : '')
                    ->visible(fn (): bool => OrderResource::canSeeManagers()),
            ])
            ->defaultSort('montage_date', 'desc')
            ->emptyStateHeading('У монтажника ще немає замовлень')
            ->paginated([20, 100])
            ->defaultPaginationPageOption(20);
    }
}
