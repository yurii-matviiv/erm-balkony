<?php

namespace App\Filament\Resources\LeadResource\Pages;

use App\Filament\Concerns\HasEditClientAction;
use App\Filament\Concerns\HasPageDocs;
use App\Filament\Resources\LeadResource;
use App\Filament\Resources\OrderResource;
use App\Models\Lead;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;

/**
 * The dedicated "переглянути/редагувати лід" page — per explicit request,
 * a lead is CREATED in a modal (see ListLeads) but VIEWED/EDITED on its
 * own full page, not a modal. This is also where things that only make
 * sense for an already-existing lead belong going forward (reminders,
 * call history, etc. — not built yet) and where the page documentation
 * lives (see getFooter()), instead of cluttering the list page.
 *
 * Registering this page in LeadResource::getPages() is what makes the
 * table's edit action navigate here instead of opening a modal — see the
 * SupplierResource docblock for the reverse case of that same mechanism.
 *
 * getHeader() adds two things ABOVE the edit form, per explicit request:
 * 1. A clickable stage stepper ("статус-бар") — a visual + interactive
 *    shortcut for the same `stage` field that's also in the form below,
 *    so a manager doesn't have to open a dropdown just to see/advance
 *    where a lead is in the funnel.
 * 2. An "action bar" with exactly ONE button — the next concrete thing to
 *    do, not just a status. Two steps exist so far: "Створити заявку на
 *    замір" (createMeasurementAction — shown until a measurement exists),
 *    then "Створити замовлення" (createOrderAction — shown once a
 *    measurement exists but no Order yet). The intermediate funnel steps
 *    (КП, узгодження, ...) intentionally do NOT have action buttons yet —
 *    per the request, this is being built one step at a time. Once an
 *    Order exists, the action bar shows a link to it instead of a button.
 *
 * form() is overridden (NOT shared with LeadResource::form(), which is
 * still used as-is by the create modal) per explicit request: on the
 * create modal, picking/creating a Client via the `client_id` select makes
 * sense — but on an EXISTING lead, that same select was confusing (it
 * looked like "add a client" even though one was already attached, and a
 * lead's client should not be silently re-pointed at a different person
 * via a search box). Here the client is shown read-only (ПІБ/phone/address)
 * with an explicit "Редагувати клієнта" modal for actually changing their
 * details — see editClientAction().
 */
class EditLead extends EditRecord
{
    use HasEditClientAction;
    use HasPageDocs;

    protected static string $resource = LeadResource::class;

    /** No save buttons anywhere — form auto-saves on every field change. */
    protected function getFormActions(): array
    {
        return [];
    }

    /**
     * Auto-save silently whenever any form field updates.
     * Same pattern as EditOrder::updated() — see its docblock.
     */
    public function updated(string $name): void
    {
        if (! str_starts_with($name, 'data.')) {
            return;
        }
        try {
            $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
        } catch (\Illuminate\Validation\ValidationException) {
            // ignore — user hasn't finished filling the field yet
        } catch (\Throwable) {
            // ignore other errors silently
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            // Old CRM's "Прийняти заявку": an unassigned lead is taken by
            // choosing yourself as its manager. Shown only while unassigned.
            Action::make('acceptLead')
                ->label('Прийняти заявку')
                ->icon('heroicon-o-check-circle')
                ->color('primary')
                ->visible(fn (): bool => $this->getRecord()->manager_id === null)
                ->action(function (): void {
                    $this->getRecord()->update(['manager_id' => auth()->id()]);
                    $this->fillForm();
                }),
            DeleteAction::make(),
        ];
    }

    public function getHeader(): ?View
    {
        return view('filament.resources.lead-resource.pages.lead-status-bar', [
            'record' => $this->getRecord(),
            'stageOptions' => Lead::stageOptions(),
            'createMeasurementAction' => $this->createMeasurementAction(),
            'createOrderAction' => $this->createOrderAction(),
            'createReminderAction' => $this->createReminderAction(),
            'reminders' => $this->getRecord()->reminders()->with('user')->get(),
        ]);
    }

    /**
     * Rendered automatically below the edit form by Filament's page
     * layout — no custom $view needed, see HasPageDocs docblock.
     */
    public function getFooter(): ?View
    {
        // Two doc blocks: the old CRM's full "Інструкція / обов'язки" (ported
        // verbatim for managers moving over) and the new-system stage/status
        // explanation. Each carries its own edit action.
        $block = fn (string $section, string $title): array => [
            'doc' => $this->getPageDoc('leads', $section),
            'canManage' => $this->canManagePageDocs(),
            'defaultTitle' => $title,
            'action' => $this->pageDocAction('leads', $section, $title),
        ];

        return view('filament.resources.lead-resource.pages.lead-docs', [
            'instruction' => $block('instruction', "Інструкція / обов'язки"),
            'statuses' => $block('statuses', 'Статуси заявок'),
        ]);
    }

    /**
     * Replaces LeadResource::form() for this page specifically — see class
     * docblock. The Client is display-only here (TextEntry, not an input);
     * actually changing the client's own data goes through
     * editClientAction()'s modal instead of inline fields.
     *
     * ->columns(1) here is NOT optional decoration — EditRecord::defaultForm()
     * forces $schema->columns(2) on any schema that hasn't set its own
     * columns (see vendor/filament/filament EditRecord.php), which made the
     * three top-level Sections below ("cards") pair up two-per-row on wide
     * screens instead of stacking. Each Section stays full-width; columns
     * INSIDE a Section (e.g. "Заявка" below) are still fine/desired — those
     * already collapse to 1 column on small screens on their own.
     */
    public function form(Schema $schema): Schema
    {
        // One card in the old CRM's order (ФИО → номер → адреса → спосіб →
        // ресурс → тип → профіль → статус → коментарі → розрахунки), labels on
        // the left, so managers moving over see the same layout.
        return $schema->columns(1)->components([
            Section::make('Картка ліда')
                ->extraAttributes(['class' => 'record-card'])
                ->inlineLabel()
                ->columns(1)
                ->schema([
                    SchemaView::make('filament.resources.lead-resource.components.client-row')
                        ->viewData(fn (?Lead $record): array => [
                            'record' => $record,
                            'editClientAction' => $this->editClientAction(),
                        ]),

                    SchemaView::make('filament.resources.lead-resource.components.phone-links')
                        ->viewData(fn (?Lead $record): array => ['record' => $record]),

                    SchemaView::make('filament.resources.lead-resource.components.address-row')
                        ->viewData(fn (?Lead $record): array => [
                            'record' => $record,
                            'editAddressAction' => $this->editAddressAction(),
                        ]),

                    Select::make('source')
                        ->label('Спосіб звернення')
                        ->options(Lead::sourceOptions())
                        ->required()
                        ->live(),

                    Group::make()
                        ->relationship('marketingData')
                        ->schema([
                            Select::make('site_source')
                                ->label('Ресурс')
                                ->options(Lead::siteSourceOptions())
                                ->live(),
                        ]),

                    Select::make('serviceTypes')
                        ->label('Тип звернення')
                        ->relationship('serviceTypes', 'name')
                        ->multiple()
                        ->preload()
                        ->required()
                        ->live(),

                    Select::make('profile')
                        ->label('Тип профілю')
                        ->options(Lead::profileOptions())
                        ->placeholder('це поле обов\'язкове')
                        ->live(),

                    Select::make('stage')
                        ->label('Статус')
                        ->options(Lead::stageOptions())
                        ->required()
                        ->live(),

                    Select::make('status')
                        ->label('Результат')
                        ->options(Lead::statusOptions())
                        ->required()
                        ->live(),

                    Textarea::make('lost_reason')
                        ->label('Причина втрати')
                        ->lazy()
                        ->visible(fn (Get $get): bool => $get('status') === 'lost'),

                    Textarea::make('comment')
                        ->label('Коментар / + тип клієнта')
                        ->placeholder('вкажіть тип клієнта, які цінності його цікавлять')
                        ->rows(3)
                        ->lazy(),

                    Textarea::make('comment_call')
                        ->label('Що планую робити далі')
                        ->placeholder('обов\'язково для заповнення! Вкажіть, що будемо робити, та коли')
                        ->rows(3)
                        ->lazy(),

                    Textarea::make('comment_callback')
                        ->label('Результат/дія: чому замовив або ні')
                        ->rows(3)
                        ->lazy(),

                    TextInput::make('advance_calculation1')
                        ->label('Розрахунок №1')
                        ->helperText('Якщо ви відправили розрахунок клієнту, він повинен бути тут, обов\'язково!')
                        ->lazy(),

                    Section::make('Додаткові поля для розрахунків')
                        ->collapsed()
                        ->compact()
                        ->inlineLabel()
                        ->schema([
                            TextInput::make('advance_calculation2')->label('Розрахунок №2')->lazy(),
                            TextInput::make('advance_calculation3')->label('Розрахунок №3')->lazy(),
                        ]),
                ]),
        ]);
    }

    /**
     * "Змінити адресу" — the old CRM had one free-text address input; here the
     * address is shown as plain text and edited in a modal with the structured
     * site_* fields. Data is resolved eagerly (plain array, not a Closure) —
     * see the fillForm() gotcha in CLAUDE.md.
     */
    public function editAddressAction(): Action
    {
        $record = $this->getRecord();

        return Action::make('editAddress')
            ->label('Змінити адресу')
            ->icon('heroicon-o-pencil-square')
            ->link()
            ->modalHeading('Адреса об\'єкта заявки')
            ->modalDescription('Де виконуються роботи за цією заявкою. Якщо потрібно змінити адресу клієнта — скористайтеся кнопкою «Редагувати клієнта».')
            ->modalSubmitActionLabel('Зберегти')
            ->modalCancelActionLabel('Скасувати')
            ->modalWidth('lg')
            ->fillForm($record->only(['site_city', 'site_street', 'site_house_number', 'site_apartment_number', 'site_floor']))
            ->schema([
                TextInput::make('site_city')->label('Місто')->maxLength(255),
                TextInput::make('site_street')->label('Вулиця')->maxLength(255),
                TextInput::make('site_house_number')->label('Будинок')->maxLength(20),
                TextInput::make('site_apartment_number')->label('Квартира')->maxLength(20),
                TextInput::make('site_floor')->label('Поверх')->maxLength(20),
            ])
            ->action(function (array $data): void {
                $this->getRecord()->update($data);
                $this->fillForm();
            });
    }

    /** Old CRM "Додати нагадування для ліда" — a reminder for the current manager tied to this lead. */
    public function createReminderAction(): Action
    {
        return Action::make('createReminder')
            ->label('Створити нагадування')
            ->icon('heroicon-o-bell-alert')
            ->color('warning')
            ->modalHeading('Нове нагадування')
            ->modalSubmitActionLabel('Зберегти')
            ->modalCancelActionLabel('Скасувати')
            ->schema([
                Textarea::make('reminder_text')->label('Текст нагадування')->required()->rows(3),
                \Filament\Forms\Components\DateTimePicker::make('reminder_datetime')->label('Дата і час нагадування')->seconds(false)->required(),
                Select::make('reminder_type')->label('Тип нагадування')->options(\App\Models\ManagerReminder::typeOptions())->default('important_urgent')->required(),
            ])
            ->action(function (array $data): void {
                $this->getRecord()->reminders()->create($data + ['user_id' => auth()->id()]);
            });
    }

    /** Inline edit from the reminders table (text / date / done flag), saved on blur/change. */
    public function updateReminderField(int $id, string $field, mixed $value): void
    {
        if (! in_array($field, ['reminder_text', 'reminder_datetime', 'is_completed'], true)) {
            return;
        }

        $reminder = $this->getRecord()->reminders()->whereKey($id)->first();
        $reminder?->update([$field => $field === 'is_completed' ? (bool) $value : $value]);
    }

    /**
     * Sets the stage directly from a status-bar button click — bypasses
     * the form entirely (no "Save" needed), then refreshes the form so
     * its own Stage select doesn't show a now-stale value.
     */
    public function setStage(string $stage): void
    {
        $this->getRecord()->update(['stage' => $stage]);

        $this->fillForm();
    }

    public function createMeasurementAction(): Action
    {
        return Action::make('createMeasurement')
            ->label('Створити заявку на замір')
            ->icon('heroicon-o-calendar-days')
            ->modalHeading('Заявка на замір')
            ->schema([
                DatePicker::make('scheduled_date')
                    ->label('Дата заміру')
                    ->required()
                    ->native(false),

                TimePicker::make('scheduled_time')
                    ->label('Час заміру')
                    ->seconds(false),

                Select::make('surveyor_id')
                    ->label('Замірник (відповідальний)')
                    ->helperText('Саме ця людина вважається відповідальною за бригаду — навіть якщо монтажник інший.')
                    ->options(fn () => User::query()->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->required(),

                Select::make('installer_id')
                    ->label('Монтажник')
                    ->helperText('Можна вибрати ту саму людину, що й замірника, якщо бригада одна.')
                    ->options(fn () => User::query()->orderBy('name')->pluck('name', 'id'))
                    ->searchable(),

                Textarea::make('comment')
                    ->label('Коментар')
                    ->columnSpanFull(),
            ])
            ->action(function (array $data): void {
                $record = $this->getRecord();

                $record->measurements()->create($data);
                $record->update(['stage' => 'measurement_scheduled']);

                $this->fillForm();
            });
    }

    /**
     * The second step of the action bar — see class docblock. Asks for
     * explicit confirmation first (per request: this is an easy thing to
     * click by accident, and creating an Order isn't something to undo
     * casually), naming the actual client + address in the confirmation
     * text rather than a generic "are you sure?" so a manager can catch a
     * wrong-lead click before it happens.
     *
     * Crew (surveyor/installer) is carried over from the lead's latest
     * measurement, if any — per the project's standing rule, surveyor_id
     * is always the responsible person (see LeadMeasurement/Order
     * migration docblocks), so that's what's copied, not installer_id
     * alone.
     */
    public function createOrderAction(): Action
    {
        return Action::make('createOrder')
            ->label('Створити замовлення')
            ->icon('heroicon-o-shopping-bag')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Підтвердіть створення замовлення')
            ->modalDescription(function (): string {
                $record = $this->getRecord();
                $client = $record->client;
                $address = $record->site_address ?? $client?->full_address ?? 'адресу не вказано';

                return "Підтвердіть створення нового замовлення для клієнта \"{$client?->full_name}\", адреса: {$address}.";
            })
            ->modalSubmitActionLabel('Створити замовлення')
            ->action(function () {
                $record = $this->getRecord();
                $measurement = $record->latestMeasurement();

                $order = $record->orders()->create([
                    'client_id' => $record->client_id,
                    'manager_id' => $record->manager_id,
                    'surveyor_id' => $measurement?->surveyor_id,
                    'installer_id' => $measurement?->installer_id,
                    'address' => $record->site_address ?? $record->client?->full_address ?? '',
                    'stage' => 'new',
                    'status' => 'open',
                ]);

                $record->update(['stage' => 'closing']);

                $this->fillForm();

                return $this->redirect(OrderResource::getUrl('edit', ['record' => $order]));
            });
    }
}
