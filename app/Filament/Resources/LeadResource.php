<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LeadResource\Pages\EditLead;
use App\Filament\Resources\LeadResource\Pages\ListLeads;
use App\Models\Client;
use App\Models\Lead;
use BackedEnum;
use App\Support\RolePanels;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Models\User;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * "Заявки" (leads) — the start of the sales pipeline.
 *
 * Mixed pattern, per explicit request: CREATE still happens in a modal
 * (no `create` page registered — see ListLeads), but VIEW/EDIT happens on
 * a dedicated page (EditLead) — not a modal. A lead is something you keep
 * coming back to (reminders, history, etc. planned), so it deserves real
 * page real estate, including the "Документація сторінки" block — see
 * EditLead::getFooter(). Registering the `edit` page below is what makes
 * the table's edit action navigate there instead of opening a modal (the
 * opposite of the trick described in SupplierResource's docblock).
 *
 * Deliberately does NOT collect name/phone/address as flat fields on this
 * form — that data belongs to Client (see that model). The `client_id`
 * select below IS the phone-search step described in the request: typing
 * searches existing clients by phone/name, picking one reuses that client
 * (and auto-flags this as a "Повторне звернення"); the "+" button creates
 * a brand-new Client inline without leaving this modal.
 */
class LeadResource extends Resource
{
    protected static ?string $model = Lead::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static string|\UnitEnum|null $navigationGroup = 'Заявки';

    protected static ?string $navigationLabel = 'Лід - заявка';

    protected static ?string $modelLabel = 'заявка';

    protected static ?string $pluralModelLabel = 'заявки';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('client_id')
                ->label('Телефон / клієнт')
                ->relationship('client', 'phone')
                ->searchable(['phone', 'last_name', 'first_name'])
                ->getOptionLabelFromRecordUsing(fn (Client $record): string => "{$record->phone} — {$record->full_name}")
                ->preload()
                ->required()
                ->live()
                ->afterStateUpdated(function (?string $state, callable $set): void {
                    // The only way a lead can be "повторне звернення" is if
                    // this client already has at least one EARLIER lead in
                    // the new system — not merely "exists in clients",
                    // since ~12k legacy clients were synced from the old
                    // CRM and would otherwise never count as "нова заявка".
                    $hasPriorLead = $state && Lead::where('client_id', $state)->exists();
                    $set('application_type', $hasPriorLead ? 'repeat' : 'new');
                })
                ->createOptionForm([
                    TextInput::make('phone')
                        ->label('Телефон')
                        ->tel()
                        ->required()
                        ->unique('clients', 'phone')
                        ->maxLength(30),

                    TextInput::make('last_name')->label('Прізвище')->maxLength(255),
                    TextInput::make('first_name')->label("Ім'я")->required()->maxLength(255),
                    TextInput::make('middle_name')->label('По батькові')->maxLength(255),
                    TextInput::make('email')->label('Email')->email()->maxLength(255),

                    TextInput::make('street')->label('Вулиця')->maxLength(255),
                    TextInput::make('house_number')->label('Будинок')->maxLength(20),
                    TextInput::make('apartment_number')->label('Квартира')->maxLength(20),
                    TextInput::make('floor')->label('Поверх')->maxLength(20),
                    TextInput::make('city')->label('Місто')->default('Київ')->required()->maxLength(255),
                ])
                ->createOptionModalHeading('Новий клієнт')
                ->rule(function (?Lead $record): \Closure {
                    // $record is the lead being edited (null when
                    // creating). Without excluding it by id, editing an
                    // existing lead created today would trip over its own
                    // row and refuse to save.
                    return function (string $attribute, $value, \Closure $fail) use ($record): void {
                        $query = Lead::where('client_id', $value)->whereDate('created_at', today());

                        if ($record) {
                            $query->whereKeyNot($record->getKey());
                        }

                        if ($query->exists()) {
                            $fail('У цього клієнта вже є заявка сьогодні. Додайте потрібну послугу до існуючої заявки замість створення нової.');
                        }
                    };
                })
                ->helperText('Введіть телефон — якщо клієнт уже є в базі, оберіть його зі списку (буде повторне звернення). Якщо немає — натисніть "+", щоб створити новий контакт.'),

            Select::make('application_type')
                ->label('Тип заявки')
                ->options(Lead::applicationTypeOptions())
                ->default('new')
                ->disabled()
                ->dehydrated()
                ->helperText('Визначається автоматично за вибраним клієнтом.'),

            Select::make('source')
                ->label('Звідки отримали заявку')
                ->options(Lead::sourceOptions())
                ->default('call')
                ->required(),

            Select::make('serviceTypes')
                ->label('Тип звернення (послуги)')
                ->relationship('serviceTypes', 'name')
                ->multiple()
                ->preload()
                ->required()
                ->helperText('Можна вибрати декілька — якщо клієнт цікавиться одразу кількома послугами.'),

            Select::make('stage')
                ->label('Етап воронки')
                ->options(Lead::stageOptions())
                ->default('new')
                ->required(),

            Select::make('status')
                ->label('Статус')
                ->options(Lead::statusOptions())
                ->default('open')
                ->required()
                ->live(),

            Textarea::make('lost_reason')
                ->label('Причина втрати')
                ->visible(fn (Get $get): bool => $get('status') === 'lost')
                ->columnSpanFull(),

            Textarea::make('comment')
                ->label('Коментар')
                ->columnSpanFull(),
        ]);
    }

    /**
     * Visibility rule copied from the old CRM (Views/Enquiry/index.php: a row is
     * rendered only when `$user_name == manager_name || empty(manager_name)`,
     * and the admin role sees everything): in the manager panel a manager sees
     * their OWN leads plus the still UNASSIGNED ones (new leads anybody may
     * accept). Once somebody accepts a lead it disappears for everyone else.
     * Other panels/roles keep the full list.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user && RolePanels::idForUser($user) === RolePanels::MANAGER) {
            $query->where(fn (Builder $q) => $q->where('manager_id', $user->getKey())->orWhereNull('manager_id'));
        }

        return $query;
    }

    /** Count of unaccepted leads (old CRM: `who_added_user_id IS NULL AND date > 2024-01-01`). */
    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();

        if (! $user || RolePanels::idForUser($user) !== RolePanels::MANAGER) {
            return null;
        }

        $count = Lead::query()->whereNull('manager_id')->where('created_at', '>', '2024-01-01')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    /**
     * Roles that oversee all leads and therefore see the manager column and
     * the "Менеджер" filter (checked against the ACTIVE role).
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
        return $table
            // Column order mirrors the old CRM list (date, status, client,
            // type, address, contact method, comment) so managers moving over
            // find everything where they expect it. The list is view-only:
            // clicking anywhere on a row opens the lead (recordUrl below);
            // stage changes happen on the lead page.
            ->columns([
                TextColumn::make('created_at')
                    ->toggleable()
                    ->label('Дата')
                    ->formatStateUsing(fn ($state): string => $state->locale('uk')->isoFormat('dd DD.MM'))
                    ->description(fn (Lead $record): string => $record->created_at->format('H:i'))
                    ->sortable(),

                // Who owns the lead — surname only (names are stored surname-first),
                // right after the date. Hidden in the manager panel: a manager
                // already sees only their own (and unassigned) leads.
                TextColumn::make('manager.name')
                    ->toggleable()
                    ->label('Менеджер')
                    ->formatStateUsing(fn (?string $state): string => $state ? (explode(' ', trim($state))[0] ?: $state) : '')
                    ->placeholder('—')
                    ->visible(fn (): bool => static::canSeeManagers()),

                // Old CRM had ONE merged status; the new model splits it into
                // stage + status. Show the final outcome when there is one,
                // otherwise the funnel stage.
                TextColumn::make('stage')
                    ->toggleable()
                    ->label('Статус')
                    ->badge()
                    ->state(fn (Lead $record): string => match ($record->status) {
                        'won' => 'Угода укладена',
                        'lost' => 'Втрачено',
                        default => Lead::stageOptions()[$record->stage] ?? '—',
                    })
                    ->color(fn (Lead $record): string => match ($record->status) {
                        'won' => 'success',
                        'lost' => 'danger',
                        default => 'info',
                    }),

                TextColumn::make('client.full_name')
                    ->toggleable()
                    ->label('Клієнт')
                    ->description(fn (Lead $record): ?string => $record->client?->phone)
                    ->searchable(['last_name', 'first_name', 'phone']),

                TextColumn::make('serviceTypes.name')
                    ->toggleable()
                    ->label('Тип звернення')
                    ->badge()
                    ->separator(','),

                TextColumn::make('site_address')
                    ->toggleable()
                    ->label('Адреса об\'єкта')
                    ->state(fn (Lead $record): ?string => $record->site_address)
                    ->placeholder('—')
                    ->wrap()
                    ->searchable(['site_city', 'site_street']),

                TextColumn::make('source')
                    ->toggleable()
                    ->label('Спосіб звернення')
                    ->formatStateUsing(fn (?string $state): string => Lead::sourceOptions()[$state] ?? '—'),

                TextColumn::make('comment')
                    ->toggleable()
                    ->label('Коментар')
                    ->limit(60)
                    ->tooltip(fn (Lead $record): ?string => $record->comment)
                    ->placeholder('—'),

                TextColumn::make('comment_sales_head')
                    ->toggleable()
                    ->label('Коментар керівника')
                    ->color('danger')
                    ->limit(60)
                    ->tooltip(fn (Lead $record): ?string => $record->comment_sales_head)
                    ->placeholder('—'),

                TextColumn::make('comment_call')
                    ->toggleable()
                    ->label('Що планую робити далі')
                    ->limit(60)
                    ->tooltip(fn (Lead $record): ?string => $record->comment_call)
                    ->placeholder('—'),

                TextColumn::make('application_type')
                    ->label('Тип заявки')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'repeat' ? 'warning' : 'success')
                    ->formatStateUsing(fn (?string $state): string => Lead::applicationTypeOptions()[$state] ?? '—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // Manager checkboxes: tick one or several managers (or
                // "Не призначено"); untick to widen again. Only for roles that
                // oversee all leads.
                Filter::make('managers')
                    ->label('Менеджер')
                    ->visible(fn (): bool => static::canSeeManagers())
                    ->schema([
                        CheckboxList::make('ids')
                            ->label('Менеджер')
                            ->options(function (): array {
                                $users = User::query()
                                    ->whereIn('id', Lead::query()->whereNotNull('manager_id')->select('manager_id')->distinct())
                                    ->orderBy('name')
                                    ->get()
                                    ->mapWithKeys(fn (User $u): array => [
                                        $u->id => implode(' ', array_slice(preg_split('/\s+/u', trim($u->name)), 0, 2)),
                                    ])
                                    ->all();

                                return ['none' => 'Не призначено'] + $users;
                            })
                            ->columns(1),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $ids = array_filter((array) ($data['ids'] ?? []), fn ($v): bool => filled($v));

                        if ($ids === []) {
                            return $query;
                        }

                        $withNone = in_array('none', $ids, true);
                        $real = array_values(array_diff($ids, ['none']));

                        return $query->where(function (Builder $q) use ($real, $withNone): void {
                            if ($real !== []) {
                                $q->whereIn('leads.manager_id', $real);
                            }

                            if ($withNone) {
                                $q->orWhereNull('leads.manager_id');
                            }
                        });
                    })
                    ->indicateUsing(function (array $data): ?string {
                        $count = count(array_filter((array) ($data['ids'] ?? []), fn ($v): bool => filled($v)));

                        return $count > 0 ? "Менеджери: {$count}" : null;
                    }),

                // Same name as the column (and the same values it shows).
                SelectFilter::make('source')
                    ->label('Спосіб звернення')
                    ->options(Lead::sourceOptions()),

                // Mirrors the "Статус" column exactly: a final outcome
                // (Угода укладена / Втрачено) when there is one, otherwise
                // the funnel stage — so what you pick is what you see.
                SelectFilter::make('lead_status')
                    ->label('Статус')
                    ->options(Lead::stageOptions() + [
                        'won' => Lead::statusOptions()['won'],
                        'lost' => Lead::statusOptions()['lost'],
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (blank($value)) {
                            return $query;
                        }

                        if (in_array($value, ['won', 'lost'], true)) {
                            return $query->where('leads.status', $value);
                        }

                        return $query
                            ->where('leads.stage', $value)
                            ->where(fn (Builder $q) => $q->whereNull('leads.status')->orWhereNotIn('leads.status', ['won', 'lost']));
                    }),

                SelectFilter::make('serviceTypes')
                    ->label('Тип звернення')
                    ->relationship('serviceTypes', 'name'),
            ])
            ->recordUrl(fn (Lead $record): string => static::getUrl('edit', ['record' => $record]))
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->paginationPageOptions([20, 100])
            ->defaultPaginationPageOption(20)
            ->defaultSort('created_at', 'desc')
            ->persistColumnsInSession();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLeads::route('/'),
            'edit' => EditLead::route('/{record}/edit'),
        ];
    }
}
