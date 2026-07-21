<?php

namespace App\Filament\Pages\Finance;

use App\Models\Settlement;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * "Взаєморозрахунки" — settlements between the company and its owners.
 *
 * Rules of the module live in CLAUDE.md "Модуль Взаєморозрахунки" (the
 * single source of truth). Short version:
 *
 *   - Інкасація  (collection): company → shared balance.
 *   - Переказ    (transfer):   shared balance → participant's account.
 *   - Shared balance is COMPUTED (collections - transfers), never stored.
 *   - Participants (Юрій/Сергій) are users referenced by id, configured
 *     via the "Учасники" header action (app_settings) — no hardcoded ids.
 *   - Every row records created_by + created_at (exact time) — the audit
 *     trail the user explicitly asked for.
 *   - Editing/deleting rows is super_admin-only (real roles, not the
 *     active-role switcher) — everyone else only adds operations.
 *
 * Access: default-deny via RequiresViewPermission ('View:Settlements') —
 * nobody but super_admin sees the page until it is enabled for a role in
 * "Бокова панель" (SidebarPermissionSync grants the permission).
 */
class Settlements extends Page implements HasTable
{
    use \App\Filament\Concerns\RequiresViewPermission;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static string|\UnitEnum|null $navigationGroup = 'Фінанси';

    protected static ?string $navigationLabel = 'Взаєморозрахунки';

    protected static ?string $title = 'Взаєморозрахунки';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.finance.settlements';

    protected Width|string|null $maxContentWidth = Width::Full;

    // ──────────────────────────────────────────────
    // Date filter — same page-level date bar as "Платежі" (always visible,
    // never hidden inside the table filter panel — explicit user request
    // repeated for this module: "потрібен наш фільтр дат на початку").
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
    // Indicators
    // ──────────────────────────────────────────────

    /**
     * KPI card data for the blade view — exactly THREE cards (per ТЗ):
     * balance + one per participant.
     *
     * Each participant's 'transferred' respects the date bar (period
     * view); 'balance' (collections - transfers) is ALL-TIME on purpose
     * (user decision 2026-07-21: first card shows the remainder only) —
     * a remainder only makes sense over the whole history, and the card
     * says so.
     *
     * @return array{
     *     participants: array<int, array{user: User, transferred: float}>,
     *     balance: float,
     * }
     */
    public function getIndicators(): array
    {
        $inPeriod = fn (Builder $q): Builder => $q
            ->when($this->dateFrom, fn (Builder $q, string $d) => $q->whereDate('paid_at', '>=', $d))
            ->when($this->dateTo, fn (Builder $q, string $d) => $q->whereDate('paid_at', '<=', $d));

        $participants = Settlement::participants()
            ->map(fn (User $user): array => [
                'user' => $user,
                'transferred' => (float) $inPeriod(Settlement::query())
                    ->where('type', Settlement::TYPE_TRANSFER)
                    ->where('recipient_id', $user->id)
                    ->sum('amount'),
            ])
            ->all();

        $balance = (float) Settlement::where('type', Settlement::TYPE_COLLECTION)->sum('amount')
            - (float) Settlement::where('type', Settlement::TYPE_TRANSFER)->sum('amount');

        return [
            'participants' => $participants,
            'balance' => $balance,
        ];
    }

    // ──────────────────────────────────────────────
    // Table
    // ──────────────────────────────────────────────

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Settlement::query()
                ->when($this->dateFrom, fn (Builder $q, string $d) => $q->whereDate('paid_at', '>=', $d))
                ->when($this->dateTo, fn (Builder $q, string $d) => $q->whereDate('paid_at', '<=', $d)))
            ->defaultSort('paid_at', 'desc')
            ->striped()
            ->paginated([25, 50, 100])
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(3)
            ->deferFilters(false)
            ->columns([
                TextColumn::make('paid_at')
                    ->label('Дата')
                    ->date('d.m.Y')
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Операція')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Settlement::typeOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => $state === Settlement::TYPE_COLLECTION ? 'success' : 'info'),

                TextColumn::make('recipient.name')
                    ->label('Кому')
                    ->placeholder('— (на баланс)'),

                TextColumn::make('handedBy.name')
                    ->label('Хто передав')
                    ->placeholder('—')
                    ->toggleable(),

                // Sign shows the effect on the SHARED balance: collections
                // add to it, transfers take from it.
                TextColumn::make('amount')
                    ->label('Сума')
                    ->formatStateUsing(fn (Settlement $record): string => ($record->type === Settlement::TYPE_COLLECTION ? '+' : '−')
                        .number_format((float) $record->amount, 2, '.', ' ').' ₴')
                    ->alignRight()
                    ->sortable()
                    ->color(fn (Settlement $record): string => $record->type === Settlement::TYPE_COLLECTION ? 'success' : 'danger'),

                TextColumn::make('payment_method')
                    ->label('Як саме')
                    ->badge()
                    ->placeholder('—')
                    ->formatStateUsing(fn (?string $state): string => Settlement::paymentMethodOptions()[$state] ?? ($state ?? '—'))
                    ->color(fn (?string $state): string => $state === 'cash' ? 'gray' : 'indigo'),

                TextColumn::make('comment')
                    ->label('Коментар')
                    ->wrap()
                    ->limit(180)
                    ->placeholder('—')
                    ->searchable(),

                // Audit trail: who entered the row and the EXACT time —
                // explicit user requirement for this module.
                TextColumn::make('creator.name')
                    ->label('Хто вніс'),

                TextColumn::make('created_at')
                    ->label('Внесено')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Операція')
                    ->options(Settlement::typeOptions()),

                SelectFilter::make('recipient_id')
                    ->label('Кому')
                    ->options(fn (): array => Settlement::participants()->pluck('name', 'id')->all()),

                SelectFilter::make('payment_method')
                    ->label('Як саме')
                    ->options(Settlement::paymentMethodOptions()),

                SelectFilter::make('created_by')
                    ->label('Хто вніс')
                    ->options(fn (): array => User::query()
                        ->whereIn('id', Settlement::query()->whereNotNull('created_by')->distinct()->pluck('created_by'))
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all()),
            ])
            ->recordActions([
                // Corrections are super_admin-only — everyone else only
                // ADDS operations; history must not be silently editable.
                Action::make('edit_settlement')
                    ->label('Редагувати')
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn (): bool => self::canManageRows())
                    ->modalHeading('Редагувати операцію')
                    // Type/recipient are NOT editable — a wrong direction
                    // is a delete + re-create, not a silent flip.
                    ->fillForm(fn (Settlement $record): array => $record->only(['handed_by', 'payment_method', 'amount', 'paid_at', 'comment']))
                    ->form(fn (Settlement $record): array => $record->type === Settlement::TYPE_COLLECTION
                        ? [self::handedBySelect(), ...self::amountDateCommentSchema()]
                        : self::amountDateCommentSchema())
                    ->action(function (Settlement $record, array $data): void {
                        $record->update($data);

                        Notification::make()->title('Збережено')->color('success')->send();
                    }),

                Action::make('delete_settlement')
                    ->label('Видалити')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(fn (): bool => self::canManageRows())
                    ->requiresConfirmation()
                    ->modalHeading('Видалити операцію?')
                    ->action(function (Settlement $record): void {
                        $record->delete();

                        Notification::make()->title('Видалено')->color('success')->send();
                    }),
            ]);
    }

    // ──────────────────────────────────────────────
    // Header actions: + Інкасація, + Переказ <each participant>, Учасники
    // ──────────────────────────────────────────────

    protected function getHeaderActions(): array
    {
        $actions = [
            Action::make('create_collection')
                ->label('+ Інкасація')
                ->color('success')
                ->tooltip('Забираємо гроші з компанії')
                ->modalHeading('Інкасація')
                // Exact wording requested by the user for this modal.
                ->modalDescription('Гроші попадають на загальний баланс, ми бачимо скільки грошей було інкасовано.')
                ->form([self::handedBySelect(), ...self::amountDateCommentSchema()])
                ->action(function (array $data): void {
                    Settlement::create([
                        ...$data,
                        'type' => Settlement::TYPE_COLLECTION,
                        'created_by' => auth()->id(),
                    ]);

                    Notification::make()->title('Інкасацію додано')->color('success')->send();
                }),
        ];

        // One button PER participant (explicit user decision: separate
        // "Переказ Юрію" / "Переказ Сергію" buttons instead of a select).
        foreach (Settlement::participants() as $user) {
            $actions[] = Action::make('create_transfer_'.$user->id)
                ->label('+ Переказ — '.$user->name)
                ->color('info')
                ->tooltip('Коли фактично хтось забирає гроші')
                ->modalHeading('Переказ — '.$user->name)
                // Exact wording requested by the user for this modal.
                ->modalDescription('Гроші списуються з загального балансу і зараховуються на рахунок користувача: '.$user->name.'.')
                ->form(self::amountDateCommentSchema())
                ->action(function (array $data) use ($user): void {
                    Settlement::create([
                        ...$data,
                        'type' => Settlement::TYPE_TRANSFER,
                        'recipient_id' => $user->id,
                        'created_by' => auth()->id(),
                    ]);

                    Notification::make()->title('Переказ додано')->color('success')->send();
                });
        }

        // Participant setup — super_admin only. Stored in app_settings so
        // ids are configuration, not code (жодних зашитих цифр).
        $actions[] = Action::make('configure_participants')
            ->label('Учасники')
            ->icon('heroicon-o-user-group')
            ->color('gray')
            ->visible(fn (): bool => self::canManageRows())
            ->modalHeading('Учасники взаєморозрахунків')
            ->modalDescription('Користувачі, на рахунки яких можна переказувати гроші з загального балансу. Порядок вибору визначає порядок кнопок та індикаторів.')
            ->fillForm(fn (): array => ['ids' => Settlement::participants()->pluck('id')->all()])
            ->form([
                Select::make('ids')
                    ->label('Користувачі')
                    ->multiple()
                    ->searchable()
                    // Inactive users allowed on purpose: a transfer
                    // recipient doesn't need panel access (Сергій's
                    // account is inactive and that's fine).
                    ->options(fn (): array => User::orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->required(),
            ])
            ->action(function (array $data): void {
                Settlement::saveParticipants($data['ids']);

                Notification::make()->title('Учасників збережено')->color('success')->send();
            });

        return $actions;
    }

    /**
     * Row corrections + participant setup: real super_admin role only —
     * hasRole(), NOT getActiveRoleName(): this is real authority, not a
     * view preference (see CLAUDE.md on the role switcher being visual).
     */
    private static function canManageRows(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    /**
     * "Хто передав гроші" — collections only: the person who physically
     * handed the cash over (a manager or the head of sales), separate
     * from created_by (who typed the row in). Options limited to the
     * roles that actually handle client money — same role list idea as
     * the created_by filter on "Платежі".
     */
    private static function handedBySelect(): Select
    {
        return Select::make('handed_by')
            ->label('Хто передав гроші')
            ->options(fn (): array => User::query()
                ->where('is_active', true)
                ->whereHas('roles', fn ($q) => $q->whereIn('name', [
                    'Менеджер',
                    'Керівник відділу продажу',
                ]))
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all())
            ->searchable()
            ->required();
    }

    /**
     * The shared modal form: every operation is just amount + business
     * date + optional comment; type/recipient come from the button.
     *
     * @return array<int, \Filament\Forms\Components\Component>
     */
    private static function amountDateCommentSchema(): array
    {
        return [
            // Two grouped buttons, both gray until one is picked — the
            // active one IS the stored value (explicit user request for
            // a simpler choice than a dropdown).
            \Filament\Forms\Components\ToggleButtons::make('payment_method')
                ->label('Як саме')
                ->options(Settlement::paymentMethodOptions())
                ->grouped()
                ->required(),

            TextInput::make('amount')
                ->label('Сума')
                // Financial format (project convention, see CLAUDE.md
                // "Конвенції"): thousands separated by a space while
                // typing; spaces are stripped before validation/saving.
                ->mask(\Filament\Support\RawJs::make("\$money(\$input, '.', ' ')"))
                ->stripCharacters([' ', ','])
                ->numeric()
                ->suffix('₴')
                ->minValue(0.01)
                ->required(),

            DatePicker::make('paid_at')
                ->label('Дата операції')
                ->default(today())
                ->required(),

            Textarea::make('comment')
                ->label('Коментар')
                ->rows(2)
                ->nullable(),
        ];
    }
}
