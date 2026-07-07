<?php

namespace App\Filament\Pages\Finance;

use App\Models\Expense;
use App\Models\OrderPayment;
use App\Models\PaymentLedgerEntry;
use App\Models\Supplier;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * "Платежі" — the single operational ledger over ALL money movements
 * (order_payments + expenses via the payments_ledger SQL view), with one
 * combined filter set. Replaces the former ExpenseResource ("Витрати") —
 * its create form lives here now as the "Додати витрату" header action.
 *
 * Rules of the module live in CLAUDE.md "Платежі — принципи" (the single
 * source of truth): no hardcoded numbers anywhere, migrated rows carry a
 * classification_status ('unsorted' = awaiting manual sorting — the
 * "Не розібрані" filter is that work queue), new entries are born fully
 * structured with created_by set automatically.
 *
 * Order-tied rows are read-only here (edited where they're created — the
 * order page / future "Оплати" module); general expenses are fully
 * editable in place.
 */
class Payments extends Page implements HasTable
{
    use \App\Filament\Concerns\RequiresViewPermission;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|\UnitEnum|null $navigationGroup = 'Фінанси';

    protected static ?string $navigationLabel = 'Платежі';

    protected static ?string $title = 'Платежі';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.finance.payments';

    protected Width|string|null $maxContentWidth = Width::Full;

    // ──────────────────────────────────────────────
    // Date filter — ALWAYS visible above the table (page-level Livewire
    // state, same pattern/markup as InvoiceAnalytics): pick a preset and
    // both date inputs update to show the concrete range; edit either
    // date by hand and the preset flips to "Довільний період". Explicit
    // user request — the date filter must never hide inside the table's
    // filter panel.
    // ──────────────────────────────────────────────

    public string $preset = 'last_30_days';

    public string $dateFrom = '';

    public string $dateTo = '';

    public function mount(): void
    {
        $range = \App\Services\Finance\FinanceDateRange::parseDateRange($this->preset);
        $this->dateFrom = $range['from'];
        $this->dateTo = $range['to'];
    }

    public function applyPreset(string $preset): void
    {
        $this->preset = $preset;

        if ($preset !== 'custom') {
            $range = \App\Services\Finance\FinanceDateRange::parseDateRange($preset);
            $this->dateFrom = $range['from'];
            $this->dateTo = $range['to'];
        }

        $this->resetPage();
    }

    public function dateChanged(): void
    {
        $this->preset = 'custom';
        $this->resetPage();
    }

    /** @return array<string, string> */
    public function getPresetOptions(): array
    {
        return \App\Services\Finance\FinanceDateRange::presetOptions();
    }

    // ──────────────────────────────────────────────
    // Option dictionaries (combined across both sources)
    // ──────────────────────────────────────────────

    /** @return array<string, string> */
    public static function categoryOptions(): array
    {
        return Expense::categoryOptions() + [
            'between_accounts' => 'Переказ між рахунками',
        ];
    }

    /** @return array<string, string> */
    public static function methodOptions(): array
    {
        return [
            'cash' => 'Готівка',
            'cashless' => 'Безготівковий',
            'card' => 'Картка',
            'courier' => 'Кур\'єр',
            'installer' => 'Через монтажника',
        ];
    }

    /** @return array<string, string> */
    public static function statusOptions(): array
    {
        return [
            'received' => 'Підтверджено',
            'pending' => 'Заплановано',
            'sent' => 'Відправлено',
            'canceled' => 'Скасовано',
        ];
    }

    // ──────────────────────────────────────────────
    // Table
    // ──────────────────────────────────────────────

    public function table(Table $table): Table
    {
        return $table
            // Base query already narrowed by the page-level date bar (see
            // $preset/$dateFrom/$dateTo above) — dates are NOT a table
            // filter on purpose.
            ->query(fn (): Builder => PaymentLedgerEntry::query()
                ->when($this->dateFrom, fn (Builder $q, string $d) => $q->whereDate('paid_at', '>=', $d))
                ->when($this->dateTo, fn (Builder $q, string $d) => $q->whereDate('paid_at', '<=', $d)))
            ->defaultSort('paid_at', 'desc')
            ->deferLoading()
            ->striped()
            ->paginated([25, 50, 100])
            // Remaining filters: full-width panel above the table (not the
            // cramped funnel dropdown), applied instantly — per explicit
            // user request and the "де змінюєш — там і зберігається" rule.
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(4)
            ->deferFilters(false)
            ->columns([
                TextColumn::make('paid_at')
                    ->label('Дата')
                    ->date('d.m.Y')
                    ->sortable(),

                TextColumn::make('source')
                    ->label('Джерело')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'order' ? 'Замовлення' : 'Загальна')
                    ->color(fn (string $state): string => $state === 'order' ? 'info' : 'gray'),

                TextColumn::make('order_id')
                    ->label('№ зам.')
                    ->placeholder('—')
                    // The number IS the link to the order (per explicit
                    // request) — same target as the row's "До замовлення".
                    ->url(fn (PaymentLedgerEntry $record): ?string => $record->order_id
                        ? \App\Filament\Resources\OrderResource::getUrl('edit', ['record' => $record->order_id])
                        : null)
                    ->color(fn (PaymentLedgerEntry $record): ?string => $record->order_id ? 'primary' : null)
                    ->weight('medium'),

                // Comment sits high (4th column, per explicit request) and
                // WRAPS instead of truncating — for old-CRM rows it often
                // holds the real story of the payment (including lost
                // amounts like "зняла 7 тис з моно").
                TextColumn::make('comment')
                    ->label('Коментар')
                    ->wrap()
                    ->limit(180)
                    ->tooltip(fn (?string $state): ?string => $state && mb_strlen($state) > 180 ? $state : null)
                    ->searchable(),

                TextColumn::make('direction')
                    ->label('Напрям')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state === 'income' ? 'Надходження' : 'Витрата')
                    ->color(fn (?string $state): string => $state === 'income' ? 'success' : 'danger'),

                TextColumn::make('category')
                    ->label('Категорія')
                    ->formatStateUsing(fn (?string $state): string => self::categoryOptions()[$state] ?? ($state ?? '—'))
                    ->badge()
                    ->color('warning'),

                TextColumn::make('sub_category')
                    ->label('Підкатегорія')
                    ->formatStateUsing(function (?string $state, PaymentLedgerEntry $record): string {
                        return Expense::subCategoryOptions()[$record->category][$state] ?? ($state ?? '—');
                    })
                    ->toggleable(),

                TextColumn::make('payer_name')
                    ->label('Платник/отримувач')
                    ->placeholder('—')
                    ->searchable(),

                TextColumn::make('amount')
                    ->label('Сума')
                    ->money('UAH', locale: 'uk')
                    ->sortable()
                    ->alignRight()
                    ->color(fn (PaymentLedgerEntry $record): string => $record->direction === 'income' ? 'success' : 'danger'),

                TextColumn::make('payment_method')
                    ->label('Метод')
                    ->formatStateUsing(fn (?string $state): string => self::methodOptions()[$state] ?? ($state ?? '—'))
                    ->badge()
                    ->color(fn (?string $state): string => $state === 'cash' ? 'gray' : 'indigo'),

                TextColumn::make('status')
                    ->label('Статус')
                    ->formatStateUsing(fn (?string $state): string => self::statusOptions()[$state] ?? ($state ?? '—'))
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'received' => 'success',
                        'pending' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('classification_status')
                    ->label('Розбір')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'unsorted' => 'Не розібрано',
                        // Void-not-delete group (user decision): known-
                        // useless rows (bot duplicates etc.) — kept for
                        // history, excluded from totals via status.
                        'annulled' => 'Анульовано',
                        default => 'Розібрано',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'unsorted' => 'warning',
                        'annulled' => 'gray',
                        default => 'success',
                    }),

                TextColumn::make('creator.name')
                    ->label('Вніс')
                    ->placeholder('стара CRM')
                    ->toggleable(),
            ])
            ->filters([

                SelectFilter::make('direction')
                    ->label('Напрям')
                    ->options(['income' => 'Надходження', 'outgo' => 'Витрата']),

                SelectFilter::make('source')
                    ->label('Джерело')
                    ->options(['order' => 'По замовленню', 'expense' => 'Загальна']),

                // ── Категорія + залежна підкатегорія ──────
                Filter::make('category_filter')
                    ->form([
                        Select::make('category')
                            ->label('Категорія')
                            ->options(self::categoryOptions())
                            ->live()
                            ->afterStateUpdated(fn (callable $set) => $set('sub_category', null)),
                        Select::make('sub_category')
                            ->label('Підкатегорія')
                            ->options(fn (Get $get): array => Expense::subCategoryOptions()[$get('category')] ?? [])
                            ->visible(fn (Get $get): bool => filled($get('category'))),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['category'] ?? null, fn (Builder $q, string $c) => $q->where('category', $c))
                            ->when($data['sub_category'] ?? null, fn (Builder $q, string $s) => $q->where('sub_category', $s));
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (blank($data['category'] ?? null)) {
                            return null;
                        }
                        $label = self::categoryOptions()[$data['category']] ?? $data['category'];
                        if (filled($data['sub_category'] ?? null)) {
                            $label .= ' / '.(Expense::subCategoryOptions()[$data['category']][$data['sub_category']] ?? $data['sub_category']);
                        }

                        return $label;
                    }),

                SelectFilter::make('payment_method')
                    ->label('Метод')
                    ->options(self::methodOptions()),

                SelectFilter::make('status')
                    ->label('Статус')
                    ->options(self::statusOptions()),

                SelectFilter::make('classification_status')
                    ->label('Розбір')
                    ->options([
                        'classified' => 'Розібрані',
                        'unsorted' => 'Не розібрані',
                        'annulled' => 'Анульовані',
                    ]),

                SelectFilter::make('payer_type')
                    ->label('Тип платника')
                    ->options(OrderPayment::payerTypeOptions()),

                // Suppliers are stored on ledger rows as a resolved NAME
                // (payer_name) — see OrderPaymentsSyncMapper — so this
                // filter matches by name, not id.
                SelectFilter::make('supplier')
                    ->label('Постачальник')
                    ->options(fn (): array => Supplier::orderBy('name')->pluck('name', 'name')->all())
                    ->searchable()
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['value'] ?? null, fn (Builder $q, string $name) => $q
                            ->where('payer_type', 'supplier')
                            ->where('payer_name', $name))),

                // Only roles that actually ENTER payments (per explicit
                // request): managers, head of sales, admins, founder —
                // not suppliers/installers/surveyors, who merely appear
                // in payments as counterparties.
                SelectFilter::make('created_by')
                    ->label('Хто вніс')
                    ->options(fn (): array => User::query()
                        ->where('is_active', true)
                        ->whereHas('roles', fn ($q) => $q->whereIn('name', [
                            'Менеджер',
                            'Керівник відділу продажу',
                            'Адмін',
                            'founder',
                            'super_admin',
                        ]))
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['value'] ?? null, fn (Builder $q, $id) => $q->where('created_by', $id))),

                Filter::make('order_number')
                    ->form([
                        TextInput::make('order_id')
                            ->label('№ замовлення')
                            ->numeric(),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['order_id'] ?? null, fn (Builder $q, $id) => $q->where('order_id', (int) $id)))
                    ->indicateUsing(fn (array $data): ?string => filled($data['order_id'] ?? null)
                        ? 'Замовлення №'.$data['order_id']
                        : null),
            ])
            ->recordActions([
                // Order rows: read-only here, edited where they live.
                Action::make('open_order')
                    ->label('До замовлення')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (PaymentLedgerEntry $record): ?string => $record->order_id
                        ? \App\Filament\Resources\OrderResource::getUrl('edit', ['record' => $record->order_id])
                        : null)
                    ->visible(fn (PaymentLedgerEntry $record): bool => $record->source === 'order' && $record->order_id !== null),

                // Expense rows: edit in place. Saving with a full
                // category/sub pair marks the row classified — editing IS
                // the manual sorting step for the "Не розібрані" queue.
                Action::make('edit_expense')
                    ->label('Редагувати')
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn (PaymentLedgerEntry $record): bool => $record->source === 'expense')
                    ->modalHeading('Редагувати витрату')
                    ->fillForm(fn (PaymentLedgerEntry $record): array => Expense::find($record->source_id)?->only([
                        'category', 'sub_category', 'direction', 'payment_method', 'amount', 'status', 'paid_at', 'comment',
                    ]) ?? [])
                    ->form(self::expenseFormSchema())
                    ->action(function (PaymentLedgerEntry $record, array $data): void {
                        $expense = Expense::find($record->source_id);

                        if (! $expense) {
                            return;
                        }

                        $data['classification_status'] = (filled($data['category'] ?? null) && filled($data['sub_category'] ?? null))
                            ? 'classified'
                            : 'unsorted';

                        $expense->update($data);

                        Notification::make()->title('Збережено')->color('success')->send();
                    }),

                Action::make('delete_expense')
                    ->label('Видалити')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(fn (PaymentLedgerEntry $record): bool => $record->source === 'expense')
                    ->requiresConfirmation()
                    ->modalHeading('Видалити витрату?')
                    ->action(function (PaymentLedgerEntry $record): void {
                        Expense::find($record->source_id)?->delete();

                        Notification::make()->title('Видалено')->color('success')->send();
                    }),
            ]);
    }

    // ──────────────────────────────────────────────
    // Header actions
    // ──────────────────────────────────────────────

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create_expense')
                ->label('Додати витрату')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->modalHeading('Нова витрата')
                ->form(self::expenseFormSchema())
                ->action(function (array $data): void {
                    // New entries are born fully structured (принцип 3)
                    // with authorship (принцип 4) — see CLAUDE.md.
                    Expense::create([
                        ...$data,
                        'classification_status' => 'classified',
                        'created_by' => auth()->id(),
                    ]);

                    Notification::make()->title('Витрату додано')->color('success')->send();
                }),
        ];
    }

    /**
     * Same fields the old ExpenseResource form had — that resource is
     * retired, this action is its successor.
     *
     * @return array<int, \Filament\Forms\Components\Component>
     */
    private static function expenseFormSchema(): array
    {
        return [
            Select::make('category')
                ->label('Категорія')
                ->options(Expense::categoryOptions())
                ->required()
                ->live()
                ->afterStateUpdated(fn (callable $set) => $set('sub_category', null)),

            Select::make('sub_category')
                ->label('Підкатегорія')
                ->options(fn (Get $get): array => Expense::subCategoryOptions()[$get('category')] ?? [])
                ->placeholder('Оберіть підкатегорію')
                ->nullable(),

            Select::make('direction')
                ->label('Напрямок')
                ->options(Expense::directionOptions())
                ->default('outgo')
                ->required(),

            Select::make('payment_method')
                ->label('Метод оплати')
                ->options(Expense::paymentMethodOptions())
                ->default('cash')
                ->required(),

            TextInput::make('amount')
                ->label('Сума')
                ->numeric()
                ->suffix('₴')
                ->minValue(0)
                ->required(),

            Select::make('status')
                ->label('Статус')
                ->options(Expense::statusOptions())
                ->default('received')
                ->required(),

            DatePicker::make('paid_at')
                ->label('Дата')
                ->default(today())
                ->required(),

            Textarea::make('comment')
                ->label('Коментар')
                ->rows(2)
                ->nullable(),
        ];
    }

}
