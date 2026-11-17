<?php

namespace App\Filament\Manager\Pages;

use App\Filament\Concerns\RequiresViewPermission;
use App\Filament\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\PrivatbankAccount;
use App\Models\User;
use App\Services\Salary\SalaryService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * «Модуль З/П» (стара CRM: /salary) — менеджерська зарплата по замовленнях.
 *
 * Звичайна таблиця Filament: групи «місяць · 1–15 / 16–кінець» з рядком підсумків,
 * фільтри над таблицею, дії в рядку (олівець — формула/відсоток, гроші — виплата).
 *
 * Хто що бачить (у старій — «спеціальні» user_id та ролі, тут — права, див. міграцію
 * create_salary_module_permissions):
 *  - усі з доступом до сторінки (View:SalaryModule) бачать СВОЇ замовлення (manager_id = я),
 *    не раніше 2024-01-01 (старе обмеження менеджера);
 *  - ViewAll:SalaryModule — усіх менеджерів + фільтр по менеджеру;
 *  - Pay:SalaryModule — змінювати формулу/відсоток і виплачувати з/п.
 *
 * Розрахунок — App\Services\Salary\SalaryService. Виплата = рядок order_payments
 * (витрата, category 'salary'), так само як у старій; він же видно в «Оплати» замовлення.
 * Клас живе в Manager\Pages, але зареєстрований і в адмін-панелі.
 */
class SalaryModule extends Page implements HasTable
{
    use InteractsWithTable;
    use RequiresViewPermission;

    /** Менеджер бачить замовлення не раніше цієї дати (старе обмеження). */
    private const MANAGER_NOT_BEFORE = '2024-01-01';

    protected static ?string $navigationLabel = 'Модуль З/П';

    protected static ?string $title = 'Модуль З/П';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-wallet';

    protected static \UnitEnum|string|null $navigationGroup = 'Менеджер';

    protected static ?int $navigationSort = 80;

    protected string $view = 'filament.pages.salary-module';

    protected Width|string|null $maxContentWidth = Width::Full;

    public function canViewAll(): bool
    {
        return auth()->user()?->can('ViewAll:SalaryModule') ?? false;
    }

    public function canPay(): bool
    {
        return auth()->user()?->can('Pay:SalaryModule') ?? false;
    }

    /**
     * «Прізвище Ім'я» без по батькові. У БД ПІБ лежить по-різному (повністю в `name` або `name` + `last_name`),
     * тому по батькові відсікається за закінченням (-ович/-евич/-івна/-ївна/-овна…), якщо слів більше двох.
     */
    private static function shortName(?User $user): string
    {
        if (! $user) {
            return '—';
        }

        if (filled($user->last_name)) {
            return trim($user->last_name.' '.$user->name);
        }

        $words = preg_split('/\s+/u', trim((string) $user->name)) ?: [];

        if (count($words) > 2) {
            $words = array_values(array_filter(
                $words,
                fn (string $w): bool => ! preg_match('/(ович|евич|йович|ьович|івна|ївна|овна|евна|ична|їчна)$/ui', $w),
            ));
        }

        return implode(' ', $words);
    }

    /** @return array<string, string> */
    private static function monthOptions(): array
    {
        return [
            '01' => 'Січень', '02' => 'Лютий', '03' => 'Березень', '04' => 'Квітень', '05' => 'Травень', '06' => 'Червень',
            '07' => 'Липень', '08' => 'Серпень', '09' => 'Вересень', '10' => 'Жовтень', '11' => 'Листопад', '12' => 'Грудень',
            'year' => 'Весь рік',
        ];
    }

    /** @return array<string, mixed> розрахунок по рядку таблиці */
    private function calc(Order $record): array
    {
        return app(SalaryService::class)->calculate($record->getAttributes());
    }

    /**
     * Статус зарплати по замовленню: [підпис, колір].
     * Видано — повністю виплачено; Частково — виплачено лише частину; До виплати — виплата
     * вже можлива (етап «Монтаж»/«Змонтовано»), але ще нічого не видано; Очікує — нараховано, але ще
     * рано виплачувати (етап); Не нараховано — нараховувати поки нічого (немає авансу/прибутку).
     */
    private function salaryStatus(Order $record): array
    {
        $calc = $this->calc($record);

        return match (true) {
            $calc['is_paid'] => ['Видано', 'success'],
            $calc['can_pay'] && $calc['paid'] > 0 => ['Частково', 'warning'],
            $calc['can_pay'] => ['До виплати', 'danger'],
            $calc['accrued'] <= 0 => ['Не нараховано', 'gray'],
            default => ['Очікує', 'gray'],
        };
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, ',', ' ');
    }

    /** Підсумок по колонці: рахується по замовленнях групи/таблиці (див. SalaryService::totals). */
    private function summary(string $label, string $key, bool $percent = false): Summarizer
    {
        return Summarizer::make()
            ->label($label)
            ->using(function ($query) use ($key): float {
                $ids = (clone $query)->reorder()->select('orders.id')->pluck('id');

                return (float) (app(SalaryService::class)->totals($ids)[$key] ?? 0);
            })
            ->formatStateUsing(fn ($state): string => $this->money($state).($percent ? '%' : ''));
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                $query = SalaryService::withPaymentSums(Order::query()->with('manager:id,name,last_name'));

                if (! $this->canViewAll()) {
                    $query->where('orders.manager_id', auth()->id())
                        ->whereDate('orders.created_at', '>=', self::MANAGER_NOT_BEFORE);
                }

                return $query;
            })
            // Param MUST be named $query: Filament injects the table query by name.
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->reorder()->orderBy('orders.created_at')->orderBy('orders.id'))
            ->groups([
                Group::make('period')
                    ->label('Період')
                    ->collapsible()
                    ->getKeyFromRecordUsing(fn (Order $record): string => $record->created_at->format('Y-m').'|'.($record->created_at->day <= 15 ? '1' : '2'))
                    ->getTitleFromRecordUsing(fn (Order $record): string => self::monthOptions()[$record->created_at->format('m')].' '.$record->created_at->format('Y')
                        .' · '.($record->created_at->day <= 15 ? 'з 1 по 15 число' : 'з 16 по кінець місяця'))
                    ->orderQueryUsing(fn (Builder $query, string $direction): Builder => $query
                        ->orderByRaw("DATE_FORMAT(orders.created_at, '%Y-%m') {$direction}")
                        ->orderByRaw("(DAY(orders.created_at) > 15) ASC"))
                    ->scopeQueryByKeyUsing(fn (Builder $query, string $key): Builder => $query
                        ->whereRaw("DATE_FORMAT(orders.created_at, '%Y-%m') = ?", [substr($key, 0, 7)])
                        ->whereRaw('(DAY(orders.created_at) > 15) = ?', [substr($key, -1) === '2' ? 1 : 0])),
            ])
            ->defaultGroup('period')
            ->groupingSettingsHidden()
            ->striped()
            ->paginated([50, 100, 200])
            ->defaultPaginationPageOption(100)
            ->filtersLayout(FiltersLayout::AboveContent)
            // Без кнопки «Застосувати»: таблиця змінюється одразу при зміні фільтра.
            ->deferFilters(false)
            ->filtersFormColumns(3)
            ->filters([
                SelectFilter::make('month')
                    ->label('Місяць')
                    ->options(self::monthOptions())
                    ->default(now()->format('m'))
                    ->selectablePlaceholder(false)
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null) && $data['value'] !== 'year'
                        ? $query->whereMonth('orders.created_at', (int) $data['value'])
                        : $query),
                SelectFilter::make('year')
                    ->label('Рік')
                    ->options(fn (): array => collect(range((int) now()->format('Y'), 2020))->mapWithKeys(fn (int $y): array => [$y => (string) $y])->all())
                    ->default((int) now()->format('Y'))
                    ->selectablePlaceholder(false)
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereYear('orders.created_at', (int) $data['value'])
                        : $query),
                SelectFilter::make('manager')
                    ->label('Менеджер')
                    ->placeholder('Всі')
                    ->visible(fn (): bool => $this->canViewAll())
                    ->options(fn (): array => User::query()
                        ->where('is_active', true)
                        ->whereIn('id', Order::query()->whereNotNull('manager_id')->select('manager_id')->distinct())
                        ->orderBy('name')
                        ->get(['id', 'name', 'last_name', 'is_active'])
                        ->mapWithKeys(fn (User $u): array => [$u->id => self::shortName($u)])
                        ->all())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->where('orders.manager_id', (int) $data['value'])
                        : $query),
            ])
            ->columns([
                TextColumn::make('created_at')->wrapHeader()->label('Створено')->date('d.m.Y'),

                TextColumn::make('montage_date')
                    ->wrapHeader()
                    ->label('Монтаж')
                    ->date('d.m.Y')
                    ->placeholder('—')
                    ->color(fn (Order $record): ?string => $record->montage_date && $record->montage_date->lte(today()) ? 'success' : null),

                TextColumn::make('id')
                    ->wrapHeader()
                    ->label('Замовлення')
                    ->formatStateUsing(fn ($state): string => '№'.$state)
                    ->url(fn (Order $record): string => OrderResource::getUrl('edit', ['record' => $record->id]), shouldOpenInNewTab: true)
                    ->color('primary'),

                TextColumn::make('manager_name')
                    ->wrapHeader()
                    ->label('Менеджер')
                    ->visible(fn (): bool => $this->canViewAll())
                    ->state(fn (Order $record): string => $record->manager ? self::shortName($record->manager) : '—')
                    ->description(fn (Order $record): ?string => $record->address)
                    // Ширина колонки = приблизно ширина ПІБ; довга адреса переноситься, а не розтягує таблицю.
                    ->wrap()
                    ->extraCellAttributes(['style' => 'max-width: 13rem; white-space: normal;']),

                TextColumn::make('address')
                    ->wrapHeader()
                    ->label('Адреса')
                    ->visible(fn (): bool => ! $this->canViewAll())
                    ->wrap(),

                TextColumn::make('total_price')
                    ->wrapHeader()
                    ->label('Загальна вартість')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => $this->money($state))
                    ->summarize($this->summary('Всього', 'total')),

                TextColumn::make('percent')
                    ->wrapHeader()
                    ->label('з/п %')
                    ->alignCenter()
                    ->state(fn (Order $record): float => $this->calc($record)['percent'])
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 1, ',', ' ').'%')
                    ->tooltip(fn (Order $record): string => $this->calc($record)['formula'] === 'multiplier' ? 'Формула: множник (від якості продажу)' : 'Формула: фіксований відсоток')
                    ->color(fn (Order $record): ?string => $this->calc($record)['formula'] === 'multiplier' ? 'gray' : null)
                    ->summarize($this->summary('Середній', 'avg_percent', percent: true)),

                TextColumn::make('quality')
                    ->wrapHeader()
                    ->label('Якість продажу %')
                    ->alignCenter()
                    ->state(fn (Order $record): float => $this->calc($record)['quality'])
                    ->formatStateUsing(fn ($state): string => $this->money($state))
                    ->color(fn (Order $record): ?string => $this->calc($record)['formula'] === 'percentage' && $this->calc($record)['quality'] < 15 ? 'danger' : null),

                TextColumn::make('accrued')
                    ->wrapHeader()
                    ->label('з/п нараховано')
                    ->alignEnd()
                    ->state(fn (Order $record): float => $this->calc($record)['accrued'])
                    ->formatStateUsing(fn ($state): string => $this->money($state))
                    ->tooltip(fn (Order $record): ?string => $this->calc($record)['has_advance'] ? null : 'Для цього замовлення немає авансового платежу')
                    ->summarize($this->summary('Всього', 'accrued')),

                TextColumn::make('paid')
                    ->wrapHeader()
                    ->label('з/п сплачено')
                    ->alignEnd()
                    ->state(fn (Order $record): float => $this->calc($record)['paid'])
                    ->formatStateUsing(fn ($state): string => $this->money($state))
                    ->summarize($this->summary('Всього', 'paid')),

                TextColumn::make('stage')
                    ->wrapHeader()
                    ->label('Статус замовлення')
                    ->filledBadge('gray')
                    ->state(fn (Order $record): string => $record->status === 'cancelled' ? 'Відмінено' : (Order::stageOptions()[$record->stage] ?? '—')),

                TextColumn::make('payment_status')
                    ->wrapHeader()
                    ->label('Статус оплати')
                    ->state(fn (Order $record): string => $this->calc($record)['is_paid'] ? 'сплачено' : 'не сплачено')
                    ->filledBadge(fn (?string $state): string => $state === 'сплачено' ? 'success' : 'gray'),

                TextColumn::make('balance')
                    ->wrapHeader()
                    ->label('Залишок по замовл.')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => $this->money($state))
                    ->color(fn (Order $record): string => (float) $record->balance > 0 ? 'danger' : 'success')
                    ->summarize($this->summary('Всього', 'balance')),

                // Заміна кнопки «Видати зарплату» в кінці рядка: показує, що з виплатою.
                TextColumn::make('salary_status')
                    ->wrapHeader()
                    ->label('Статус зарплати')
                    ->state(fn (Order $record): string => $this->salaryStatus($record)[0])
                    ->filledBadge(fn (Order $record): string => $this->salaryStatus($record)[1]),
            ])
            // Рядок, де зарплату виплачено повністю, підсвічується світло-зеленим (CSS у salary-module.blade.php).
            ->recordClasses(fn (Order $record): ?string => $this->calc($record)['is_paid'] ? 'salary-row-paid' : null)
            // Клік по рядку відкриває те саме вікно, що й кнопка «Видати зарплату» (крім клітинки з переходом у замовлення).
            ->recordAction('pay')
            ->recordActions([
                $this->payAction(),
            ])
            ->emptyStateHeading('Немає даних для відображення');
    }

    /**
     * «Видати зарплату» — що відбувається (так само, як у старій CRM /salary/save-payment):
     * по замовленню додається ОДИН платіж-витрата менеджеру замовлення (категорія «Зарплата»),
     * одразу зі статусом «отримано». Більше нічого: статус замовлення не змінюється, нічого не
     * надсилається, суму можна виплачувати частинами (сплачено = сума таких платежів).
     * Далі цей платіж сам потрапляє в «Платежі»/баланс рахунків, у «Оплати» замовлення та в
     * колонку «з/п сплачено» цієї таблиці.
     */
    private function payAction(): Action
    {
        return Action::make('pay')
            ->label('Видати зарплату')
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->size('sm')
            // Окремої кнопки в рядку не видно (стовпчик дій сховано CSS у salary-module.blade.php):
            // вікно відкривається кліком по рядку; де виплата ще неможлива — лише зміна формули («Зберегти»).
            ->modalHeading(fn (Order $record): string => 'Зарплата — замовлення №'.$record->id)
            ->modalDescription(function (Order $record): string {
                $calc = $this->calc($record);

                return 'Нараховано: '.$this->money($calc['accrued']).' грн · вже виплачено: '.$this->money($calc['paid'])
                    .' грн · залишок: '.$this->money(max(0, $calc['accrued'] - $calc['paid'])).' грн';
            })
            ->modalWidth(Width::Medium)
            ->modalSubmitActionLabel(fn (Order $record): string => $this->calc($record)['can_pay'] ? 'Видати' : 'Зберегти')
            ->modalCancelActionLabel('Закрити')
            ->visible(fn (): bool => $this->canPay())
            ->fillForm(function (Order $record): array {
                $calc = $this->calc($record);
                $manager = $record->manager ? self::shortName($record->manager) : '';

                return [
                    'formula_salary' => $record->formula_salary === 'multiplier' ? 'multiplier' : 'percentage',
                    'percentage_value' => $record->percentage_value !== null ? (string) (float) $record->percentage_value : (string) SalaryService::DEFAULT_PERCENT,
                    // Пропонуємо виплатити те, що ще не виплачено (у старій — повну нараховану суму).
                    'amount' => round(max(0, $calc['accrued'] - $calc['paid']), 2),
                    'payment_method' => 'cash',
                    'comment' => '★ виплата ЗП, за замовлення №'.$record->id.($manager !== '' ? ', '.$manager : ''),
                ];
            })
            ->schema(function (Order $record): array {
                $canPay = $this->calc($record)['can_pay'];

                // Нарахована сума за обраною у вікні формулою → підставляємо в «Сума».
                $refreshAmount = function (Get $get, Set $set) use ($record): void {
                    $attrs = $record->getAttributes();
                    $attrs['formula_salary'] = $get('formula_salary');
                    $attrs['percentage_value'] = $get('percentage_value') ?: SalaryService::DEFAULT_PERCENT;
                    $calc = app(SalaryService::class)->calculate($attrs);
                    $set('amount', round(max(0, $calc['accrued'] - $calc['paid']), 2));
                };

                return [
                    Select::make('formula_salary')
                        ->label('Формула нарахування')
                        ->options(SalaryService::formulaOptions())
                        ->required()
                        ->live()
                        ->afterStateUpdated($canPay ? $refreshAmount : null)
                        ->helperText('Множник: відсоток залежить від «якості продажу» (чим більший прибуток із замовлення, тим вищий відсоток).'),
                    Select::make('percentage_value')
                        ->label('Відсоток')
                        ->options(SalaryService::percentageOptions())
                        ->live()
                        ->afterStateUpdated($canPay ? $refreshAmount : null)
                        ->visible(fn (Get $get): bool => $get('formula_salary') === 'percentage')
                        ->required(fn (Get $get): bool => $get('formula_salary') === 'percentage'),
                    TextInput::make('amount')
                        ->label('Сума')
                        ->numeric()
                        ->minValue(0.01)
                        // Не можна виплатити більше, ніж залишок до нарахованого за обраною формулою.
                        ->maxValue(function (Get $get) use ($record): float {
                            $attrs = $record->getAttributes();
                            $attrs['formula_salary'] = $get('formula_salary');
                            $attrs['percentage_value'] = $get('percentage_value') ?: SalaryService::DEFAULT_PERCENT;
                            $calc = app(SalaryService::class)->calculate($attrs);

                            return round(max(0.01, $calc['accrued'] - $calc['paid']), 2);
                        })
                        ->suffix('грн')
                        ->required()
                        ->visible($canPay),
                    Select::make('payment_method')
                        ->label('Метод оплати')
                        ->options(['cash' => 'Готівка', 'cashless' => 'Безготівковий розрахунок'])
                        ->required()
                        ->visible($canPay),
                    Select::make('fop_account_id')
                        ->label('Виплата з рахунку')
                        ->options(fn (): array => PrivatbankAccount::query()->where('is_active', true)->orderBy('display_name')->pluck('display_name', 'id')->all())
                        ->placeholder('Готівка (каса)')
                        ->visible($canPay),
                    Textarea::make('comment')->label('Коментар')->rows(2)->visible($canPay),
                ];
            })
            ->action(function (array $data, Order $record): void {
                abort_unless($this->canPay(), 403);

                // 1) формула/відсоток (лише якщо змінились)
                $record->formula_salary = $data['formula_salary'];
                if ($data['formula_salary'] === 'percentage') {
                    $record->percentage_value = $data['percentage_value'];
                }
                $formulaChanged = $record->isDirty(['formula_salary', 'percentage_value']);
                $record->save();

                // Виплата ще неможлива (етап замовлення) — лише збереження формули.
                if (! $this->calc($record)['can_pay']) {
                    Notification::make()->title('Формулу з/п збережено')->success()->send();

                    return;
                }

                OrderPayment::create([
                    'order_id' => $record->id,
                    'direction' => 'outgo',
                    'payer_type' => 'expense',
                    'payer_name' => $record->manager ? self::shortName($record->manager) : null,
                    'payment_method' => $data['payment_method'],
                    'amount' => $data['amount'],
                    'status' => 'received',
                    'classification_status' => 'classified',
                    'category' => 'salary',
                    'comment' => $data['comment'] ?: null,
                    'created_by' => auth()->id(),
                    // Як у історичних рядках зі старої: дата створення є, «дата отримання» порожня.
                    'paid_at' => now()->toDateString(),
                    'fop_account_id' => $data['fop_account_id'] ?? null,
                ]);

                Notification::make()->title($formulaChanged ? 'Зарплату видано, формулу збережено' : 'Зарплату видано')->success()->send();
            });
    }
}
