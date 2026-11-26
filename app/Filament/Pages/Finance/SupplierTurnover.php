<?php

namespace App\Filament\Pages\Finance;

use App\Filament\Concerns\HasDateRangeBar;
use App\Filament\Concerns\RequiresViewPermission;
use App\Models\OrderPayment;
use App\Models\Supplier;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * «Оборот постачальників» — які суми, коли і по яких замовленнях ми заплатили
 * кожному постачальнику. Замінює однойменний модуль старої CRM
 * (/debit/turnover-by-supplier), де список постачальників був прописаний у коді.
 *
 * Джерело — order_payments (виплати постачальникам: payer_type = supplier,
 * direction = outgo, без скасованих), зв'язок із постачальником — явний
 * order_payments.supplier_id (заповнює синк, див. OrderPaymentsSyncMapper).
 * Період — стандартний бар дат (HasDateRangeBar, docs/ui/date-filter.md);
 * постачальники — картки зверху (клік вибирає, порожньо = усі). Угорі індикатори
 * (оборот, замовлення, м², ціна за м²), таблиця виплат — нижче як деталі.
 */
class SupplierTurnover extends Page implements HasTable
{
    use HasDateRangeBar;
    use InteractsWithTable;
    use RequiresViewPermission;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static string|\UnitEnum|null $navigationGroup = 'Фінанси';

    protected static ?string $navigationLabel = 'Оборот постачальників';

    protected static ?string $title = 'Оборот постачальників';

    protected static ?string $slug = 'supplier-turnover';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.pages.finance.supplier-turnover';

    protected Width|string|null $maxContentWidth = Width::Full;

    protected function defaultDatePreset(): string
    {
        return 'current_month';
    }

    /** Вибрані постачальники (клік по картці). Порожньо = усі. */
    public array $selectedSuppliers = [];

    public function toggleSupplier(int $id): void
    {
        $this->selectedSuppliers = in_array($id, $this->selectedSuppliers, true)
            ? array_values(array_diff($this->selectedSuppliers, [$id]))
            : [...$this->selectedSuppliers, $id];

        $this->resetPage();
    }

    public function clearSuppliers(): void
    {
        $this->selectedSuppliers = [];
        $this->resetPage();
    }

    /** Виплати постачальникам за вибраний період (без вибору постачальників). */
    protected function periodQuery(): Builder
    {
        return $this->applyDateRange(
            OrderPayment::query()
                ->where('payer_type', 'supplier')
                ->where('direction', 'outgo')
                ->where('status', '!=', 'canceled')
                ->whereNotNull('supplier_id'),
            'paid_at',
        );
    }

    /** Період + вибрані постачальники. */
    protected function selectedQuery(): Builder
    {
        return $this->periodQuery()
            ->when($this->selectedSuppliers, fn (Builder $q, array $ids) => $q->whereIn('supplier_id', $ids));
    }

    /**
     * Показники для індикаторів зверху. Квадратні метри рахуються по УНІКАЛЬНИХ
     * замовленнях (одне замовлення з кількома оплатами не дублюється).
     *
     * @return array{total: array<string, float|int>, suppliers: list<array<string, mixed>>}
     */
    protected function getViewData(): array
    {
        $names = Supplier::query()->pluck('name', 'id');

        $rows = $this->periodQuery()->get(['supplier_id', 'order_id', 'amount']);
        $m2 = \App\Models\Order::query()
            ->whereIn('id', $rows->pluck('order_id')->filter()->unique())
            ->pluck('square_meters', 'id');

        $aggregate = function ($group) use ($m2): array {
            $orderIds = $group->pluck('order_id')->filter()->unique();
            $sum = (float) $group->sum('amount');
            $area = (float) $orderIds->sum(fn ($id) => (float) ($m2[$id] ?? 0));

            return [
                'sum' => $sum,
                'payments' => $group->count(),
                'orders' => $orderIds->count(),
                'area' => $area,
                'per_m2' => $area > 0 ? $sum / $area : null,
            ];
        };

        $periodSum = (float) $rows->sum('amount');

        $cards = $rows->groupBy('supplier_id')
            ->map(fn ($group, $id) => ['id' => (int) $id, 'name' => $names[$id] ?? ('№' . $id)] + $aggregate($group))
            ->sortByDesc('sum')
            ->map(fn (array $c) => $c + ['share' => $periodSum > 0 ? $c['sum'] / $periodSum * 100 : 0])
            ->values()
            ->all();

        $selected = $this->selectedSuppliers
            ? $rows->whereIn('supplier_id', $this->selectedSuppliers)
            : $rows;

        return [
            'total' => $aggregate($selected),
            'suppliers' => $cards,
            'selected' => $this->selectedSuppliers,
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->selectedQuery()->with(['order', 'supplier']))
            ->defaultSort('paid_at', 'desc')
            ->persistSortInSession()
            ->deferLoading()
            ->striped()
            ->paginated([25, 50, 100])
            ->groups([
                Group::make('supplier.name')
                    ->label('Постачальник')
                    ->collapsible(),
            ])
            ->columns([
                TextColumn::make('paid_at')
                    ->label('Дата')
                    ->date('d.m.Y')
                    ->sortable(),

                TextColumn::make('supplier.name')
                    ->label('Постачальник')
                    ->searchable(),

                TextColumn::make('order_id')
                    ->label('№ зам.')
                    ->url(fn (OrderPayment $record): ?string => $record->order_id
                        ? \App\Filament\Resources\OrderResource::getUrl('edit', ['record' => $record->order_id])
                        : null)
                    ->color('primary')
                    ->weight('medium')
                    ->placeholder('—'),

                TextColumn::make('order.vendor_number')
                    ->label('№ замовл. у постачальника')
                    ->placeholder('—')
                    ->searchable(),

                TextColumn::make('order.invoice_from_supplier')
                    ->label('№ рахунку постачальника')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('order.square_meters')
                    ->label('м²')
                    ->placeholder('—')
                    ->alignRight()
                    ->toggleable(),

                TextColumn::make('payment_method')
                    ->label('Метод')
                    ->formatStateUsing(fn (?string $state): string => OrderPayment::paymentMethodOptions()[$state] ?? ($state ?? '—'))
                    ->badge()
                    ->color(fn (?string $state): string => $state === 'cash' ? 'gray' : 'info'),

                TextColumn::make('status')
                    ->label('Статус')
                    ->formatStateUsing(fn (?string $state): string => OrderPayment::statusOptions()[$state] ?? ($state ?? '—'))
                    ->badge()
                    ->color(fn (?string $state): string => $state === 'received' ? 'success' : 'warning'),

                TextColumn::make('amount')
                    ->label('Сума')
                    ->money('UAH', locale: 'uk')
                    ->sortable()
                    ->alignRight()
                    ->summarize(Sum::make()->label('Разом')->money('UAH', locale: 'uk')),
            ])
            ->emptyStateHeading('Виплат постачальникам за цей період немає')
            ->emptyStateDescription('Змініть період або вибір постачальників.');
    }
}
