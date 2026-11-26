<?php

namespace App\Filament\Pages\Finance;

use App\Filament\Concerns\HasDateRangeBar;
use App\Filament\Concerns\RequiresViewPermission;
use App\Models\Lead;
use App\Models\Order;
use App\Models\OrderPayment;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * «Оборот по профільних системах» — скільки замовлень по кожній профільній системі
 * (REHAU 60/70/80, OpenTeck, Salamander, українські…), на яку суму ми заплатили
 * постачальникам (вартість постачальника) і скільки м² це становить.
 *
 * Той самий принцип, що в SupplierTurnover: джерело — order_payments (виплати
 * постачальникам, без скасованих, з supplier_id), період — стандартний бар дат.
 * Профіль береться з ліда замовлення (leads.profile); немає ліда/профілю → «Профіль не вказано».
 * м² рахуються по УНІКАЛЬНИХ замовленнях з orders.square_meters.
 */
class ProfileTurnover extends Page implements HasTable
{
    use HasDateRangeBar;
    use InteractsWithTable;
    use RequiresViewPermission;

    public const NONE = '_none';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static string|\UnitEnum|null $navigationGroup = 'Фінанси';

    protected static ?string $navigationLabel = 'Оборот по профілях';

    protected static ?string $title = 'Оборот по профільних системах';

    protected static ?string $slug = 'profile-turnover';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.finance.profile-turnover';

    protected Width|string|null $maxContentWidth = Width::Full;

    protected function defaultDatePreset(): string
    {
        return 'current_month';
    }

    /** Вибрані профілі (клік по картці). Порожньо = усі. */
    public array $selectedProfiles = [];

    public function toggleProfile(string $key): void
    {
        $this->selectedProfiles = in_array($key, $this->selectedProfiles, true)
            ? array_values(array_diff($this->selectedProfiles, [$key]))
            : [...$this->selectedProfiles, $key];

        $this->resetPage();
    }

    public function clearProfiles(): void
    {
        $this->selectedProfiles = [];
        $this->resetPage();
    }

    protected static function profileLabel(?string $key): string
    {
        return $key === null || $key === '' || $key === self::NONE
            ? 'Профіль не вказано'
            : (Lead::profileOptions()[$key] ?? $key);
    }

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

    protected function selectedQuery(): Builder
    {
        return $this->periodQuery()->when($this->selectedProfiles, function (Builder $q, array $keys) {
            $real = array_values(array_diff($keys, [self::NONE]));
            $q->where(function (Builder $w) use ($real, $keys) {
                if ($real) {
                    $w->whereHas('order.lead', fn (Builder $l) => $l->whereIn('profile', $real));
                }
                if (in_array(self::NONE, $keys, true)) {
                    $w->orWhereDoesntHave('order.lead', fn (Builder $l) => $l->whereNotNull('profile')->where('profile', '!=', ''));
                }
            });
        });
    }

    /**
     * @return array{total: array<string, float|int|null>, profiles: list<array<string, mixed>>, selected: array}
     */
    protected function getViewData(): array
    {
        $rows = $this->periodQuery()->get(['order_id', 'amount']);

        $orders = Order::query()
            ->leftJoin('leads', 'leads.id', '=', 'orders.lead_id')
            ->whereIn('orders.id', $rows->pluck('order_id')->filter()->unique())
            ->get(['orders.id', 'orders.square_meters', 'leads.profile'])
            ->keyBy('id');

        $keyOf = function ($row) use ($orders): string {
            $p = $orders[$row->order_id]->profile ?? null;

            return ($p === null || $p === '') ? self::NONE : $p;
        };

        $aggregate = function ($group) use ($orders): array {
            $ids = $group->pluck('order_id')->filter()->unique();
            $sum = (float) $group->sum('amount');
            $area = (float) $ids->sum(fn ($id) => (float) ($orders[$id]->square_meters ?? 0));

            return [
                'sum' => $sum,
                'payments' => $group->count(),
                'orders' => $ids->count(),
                'area' => $area,
                'per_m2' => $area > 0 ? $sum / $area : null,
            ];
        };

        $periodSum = (float) $rows->sum('amount');

        $cards = $rows->groupBy($keyOf)
            ->map(fn ($group, $key) => ['key' => (string) $key, 'name' => self::profileLabel((string) $key)] + $aggregate($group))
            ->sortByDesc('sum')
            ->map(fn (array $c) => $c + ['share' => $periodSum > 0 ? $c['sum'] / $periodSum * 100 : 0])
            ->values()
            ->all();

        $selected = $this->selectedProfiles
            ? $rows->filter(fn ($r) => in_array($keyOf($r), $this->selectedProfiles, true))
            : $rows;

        return [
            'total' => $aggregate($selected),
            'profiles' => $cards,
            'selected' => $this->selectedProfiles,
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->selectedQuery()->with(['order.lead', 'supplier']))
            ->defaultSort('paid_at', 'desc')
            ->persistSortInSession()
            ->deferLoading()
            ->striped()
            ->paginated([25, 50, 100])
            ->columns([
                TextColumn::make('paid_at')->label('Дата')->date('d.m.Y')->sortable(),

                TextColumn::make('order.lead.profile')
                    ->label('Профіль')
                    ->formatStateUsing(fn (?string $state): string => self::profileLabel($state))
                    ->badge()
                    ->color('gray'),

                TextColumn::make('supplier.name')->label('Постачальник')->searchable(),

                TextColumn::make('order_id')
                    ->label('№ зам.')
                    ->url(fn (OrderPayment $record): ?string => $record->order_id
                        ? \App\Filament\Resources\OrderResource::getUrl('edit', ['record' => $record->order_id])
                        : null)
                    ->color('primary')
                    ->weight('medium')
                    ->placeholder('—'),

                TextColumn::make('order.square_meters')->label('м²')->placeholder('—')->alignRight(),

                TextColumn::make('amount')
                    ->label('Сума')
                    ->money('UAH', locale: 'uk')
                    ->sortable()
                    ->alignRight()
                    ->summarize(Sum::make()->label('Разом')->money('UAH', locale: 'uk')),
            ])
            ->emptyStateHeading('Виплат постачальникам за цей період немає')
            ->emptyStateDescription('Змініть період або вибір профілів.');
    }
}
