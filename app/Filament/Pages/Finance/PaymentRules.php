<?php

namespace App\Filament\Pages\Finance;

use App\Models\Expense;
use App\Models\OrderPayment;
use App\Models\PaymentRule;
use App\Services\Sync\SyncMapperRegistry;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/**
 * "Платіжні правила" — the single control panel for HOW payments enter
 * and get distributed in the system (user request, 2026-07-07): the admin
 * must be able to open one page and SEE how it works, and the developer
 * gets an in-system point of truth tied to CLAUDE.md ("Платежі —
 * принципи").
 *
 * Two blocks:
 *  1. МІГРАЦІЯ ЗІ СТАРОЇ CRM (read-only, rendered in the blade): every
 *     payment mapper's old→new field map with notes, live counts, and
 *     the classification totals (розібрані / не розібрані / анульовані).
 *     This is the "first system" — one-off/parallel-run import.
 *  2. ПРАВИЛА НОВОЇ СИСТЕМИ (the table below): admin-editable
 *     distribution rules for the upcoming bank-transactions import — the
 *     "second system". Nothing consumes them yet; the seeded Google Ads
 *     rule documents the intended shape.
 *
 * The PageDoc block (footer) holds the human-written principles — the
 * same ones as CLAUDE.md "Платежі — принципи", editable in-admin by
 * super_admin/founder.
 */
class PaymentRules extends Page implements HasTable
{
    use \App\Filament\Concerns\HasPageDocs;
    use \App\Filament\Concerns\RequiresViewPermission;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static string|\UnitEnum|null $navigationGroup = 'Фінанси';

    protected static ?string $navigationLabel = 'Платіжні правила';

    protected static ?string $title = 'Платіжні правила';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.finance.payment-rules';

    protected Width|string|null $maxContentWidth = Width::Full;

    // ──────────────────────────────────────────────
    // Block 1 — legacy migration overview (for the blade)
    // ──────────────────────────────────────────────

    /**
     * Payment-related mappers only — the full registry is shown on the
     * "Синхронізація" page; here we care about the money pipeline.
     *
     * @return array<int, array{label: string, old: string, new: string, oldCount: int, syncedCount: int, fieldMap: array}>
     */
    public function getPaymentMappers(): array
    {
        $keys = ['order_payments', 'general_expenses', 'google_ads_pay'];

        $result = [];

        foreach ($keys as $key) {
            $mapper = SyncMapperRegistry::find($key);

            if (! $mapper) {
                continue;
            }

            $result[] = [
                'label' => $mapper->label(),
                'old' => $mapper->oldTable(),
                'new' => $mapper->newTable(),
                'oldCount' => $mapper->oldCount(),
                'syncedCount' => $mapper->syncedCount(),
                'fieldMap' => $mapper->fieldMap(),
            ];
        }

        return $result;
    }

    /** @return array<string, int> */
    public function getClassificationTotals(): array
    {
        return [
            'classified' => OrderPayment::where('classification_status', 'classified')->count()
                + Expense::where('classification_status', 'classified')->count(),
            'unsorted' => OrderPayment::where('classification_status', 'unsorted')->count()
                + Expense::where('classification_status', 'unsorted')->count(),
            'annulled' => OrderPayment::where('classification_status', 'annulled')->count()
                + Expense::where('classification_status', 'annulled')->count(),
        ];
    }

    // ──────────────────────────────────────────────
    // Block 2 — distribution rules table
    // ──────────────────────────────────────────────

    public function table(Table $table): Table
    {
        return $table
            ->query(PaymentRule::query())
            ->defaultSort('priority')
            ->heading('Правила нової системи — розподіл банківських транзакцій')
            ->description('Використовуються майбутнім імпортом транзакцій ПриватБанку: транзакція, що відповідає умові, отримує категорію автоматично; без збігу — потрапляє в "Нез\'ясовані". Менше число пріоритету = перевіряється раніше.')
            ->columns([
                TextColumn::make('priority')
                    ->label('Пріоритет')
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Назва')
                    ->weight('medium')
                    ->searchable(),

                TextColumn::make('match_field')
                    ->label('Поле')
                    ->formatStateUsing(fn (string $state): string => PaymentRule::matchFieldOptions()[$state] ?? $state),

                TextColumn::make('match_type')
                    ->label('Умова')
                    ->formatStateUsing(fn (string $state): string => PaymentRule::matchTypeOptions()[$state] ?? $state)
                    ->badge()
                    ->color('gray'),

                TextColumn::make('pattern')
                    ->label('Шаблон')
                    ->fontFamily('mono'),

                TextColumn::make('set_category')
                    ->label('→ Категорія')
                    ->formatStateUsing(fn (?string $state): string => Expense::categoryOptions()[$state] ?? ($state ?? '—'))
                    ->badge()
                    ->color('warning'),

                TextColumn::make('set_sub_category')
                    ->label('→ Підкатегорія')
                    ->formatStateUsing(fn (?string $state, PaymentRule $record): string => Expense::subCategoryOptions()[$record->set_category][$state] ?? ($state ?? '—')),

                // Instant save on toggle — «де змінюєш, там і зберігається».
                ToggleColumn::make('is_active')
                    ->label('Активне'),
            ])
            ->recordActions([
                Action::make('edit_rule')
                    ->label('Редагувати')
                    ->icon('heroicon-o-pencil-square')
                    ->modalHeading('Редагувати правило')
                    ->fillForm(fn (PaymentRule $record): array => $record->only([
                        'name', 'priority', 'match_field', 'match_type', 'pattern',
                        'set_category', 'set_sub_category', 'note', 'is_active',
                    ]))
                    ->form(self::ruleFormSchema())
                    ->action(function (PaymentRule $record, array $data): void {
                        $record->update($data);
                        Notification::make()->title('Правило збережено')->color('success')->send();
                    }),

                Action::make('delete_rule')
                    ->label('Видалити')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (PaymentRule $record): void {
                        $record->delete();
                        Notification::make()->title('Правило видалено')->color('success')->send();
                    }),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create_rule')
                ->label('Додати правило')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->modalHeading('Нове правило розподілу')
                ->form(self::ruleFormSchema())
                ->action(function (array $data): void {
                    PaymentRule::create($data);
                    Notification::make()->title('Правило додано')->color('success')->send();
                }),
        ];
    }

    /** @return array<int, \Filament\Forms\Components\Component> */
    private static function ruleFormSchema(): array
    {
        return [
            TextInput::make('name')
                ->label('Назва')
                ->required(),

            TextInput::make('priority')
                ->label('Пріоритет (менше = раніше)')
                ->numeric()
                ->default(100)
                ->required(),

            Select::make('match_field')
                ->label('Що перевіряємо')
                ->options(PaymentRule::matchFieldOptions())
                ->default('comment')
                ->required(),

            Select::make('match_type')
                ->label('Умова')
                ->options(PaymentRule::matchTypeOptions())
                ->default('contains')
                ->required(),

            TextInput::make('pattern')
                ->label('Шаблон')
                ->helperText('Напр., GOOGLE — для списань Google Ads у призначенні платежу.')
                ->required(),

            Select::make('set_category')
                ->label('→ Категорія')
                ->options(Expense::categoryOptions())
                ->live()
                ->afterStateUpdated(fn (callable $set) => $set('set_sub_category', null))
                ->required(),

            Select::make('set_sub_category')
                ->label('→ Підкатегорія')
                ->options(fn (Get $get): array => Expense::subCategoryOptions()[$get('set_category')] ?? [])
                ->nullable(),

            Toggle::make('is_active')
                ->label('Активне')
                ->default(true),

            Textarea::make('note')
                ->label('Примітка')
                ->rows(2)
                ->nullable(),
        ];
    }

    // ──────────────────────────────────────────────
    // PageDoc — in-admin principles (point of truth)
    // ──────────────────────────────────────────────

    public function getFooter(): ?\Illuminate\Contracts\View\View
    {
        return $this->renderPageDoc(
            'payment-rules',
            'principles',
            'Принципи роботи з платежами',
        );
    }
}
