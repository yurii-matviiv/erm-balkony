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
use Filament\Schemas\Components\Grid;
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

    /**
     * Explicit page heading: the default one is derived from the record title
     * and can come out empty, so the page no longer says it is a lead.
     */
    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return 'Заявка №' . $this->getRecord()->id;
    }

    /**
     * Filament's EditRecord authorizes by `update`, so a role with only
     * View:Lead (e.g. "Керівник компанії") got 403 when opening a lead from
     * the list. Same fix as EditOrder: let view-only roles in, read-only.
     */
    protected function authorizeAccess(): void
    {
        abort_unless(
            auth()->user()?->can('Update:Lead') || auth()->user()?->can('View:Lead'),
            403,
        );
    }

    public function isReadOnly(): bool
    {
        return ! (auth()->user()?->can('Update:Lead') ?? false);
    }

    /**
     * Who may write the sales head's note ("Звернути увагу!"). Checked against
     * the ACTIVE role (same convention as EditOrder::canReassignOwnership()).
     * This is the one field a view-only head (Керівник компанії) can edit.
     */
    public function canEditSalesHeadComment(): bool
    {
        return in_array(
            auth()->user()?->getActiveRoleName(),
            ['Адмін', 'Керівник відділу продажу', 'Керівник компанії', 'super_admin'],
            true,
        );
    }

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

        // The sales head's note is saved on its own (only this column), so it
        // works even for view-only roles, and is refused for everyone else.
        if ($name === 'data.comment_sales_head') {
            if ($this->canEditSalesHeadComment()) {
                $value = trim((string) ($this->data['comment_sales_head'] ?? ''));
                $this->getRecord()->update(['comment_sales_head' => $value !== '' ? $value : null]);
            } else {
                $this->fillForm();
            }

            return;
        }

        // View-only roles can click/open every field to read its value, but
        // nothing is saved: the form snaps back to the stored data.
        if ($this->isReadOnly()) {
            $this->fillForm();
            \Filament\Notifications\Notification::make()
                ->title('Лише перегляд')
                ->body('У вас немає права змінювати дані — зміна не збережена.')
                ->warning()
                ->send();

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
            DeleteAction::make(),
        ];
    }

    /**
     * Old CRM's "Прийняти заявку": an unassigned lead is taken by choosing yourself as its manager.
     * Rendered in the action bar (lead-status-bar view) — getHeader() replaces Filament's header, so a header action would never show.
     */
    public function acceptLeadAction(): Action
    {
        return Action::make('acceptLead')
            ->label('Прийняти заявку')
            ->icon('heroicon-o-check-circle')
            ->color('primary')
            ->visible(fn (): bool => ! $this->isReadOnly() && $this->getRecord()->manager_id === null)
            ->action(function (): void {
                $this->getRecord()->update(['manager_id' => auth()->id()]);
                $this->fillForm();
            });
    }

    /** Full-screen explanation of stages and what is automatic (kept in sync with docs/STAGES.md). Shown in the action bar. */
    public function stagesInstructionAction(): Action
    {
        return Action::make('stagesInstruction')
            ->label('Інструкція')
            ->icon('heroicon-o-question-mark-circle')
            ->color('gray')
            ->outlined()
            ->modalHeading('Етапи та результат заявки — як це працює')
            ->modalWidth(\Filament\Support\Enums\Width::Screen)
            ->modalContent(fn () => view('filament.resources.lead-resource.stages-instruction'))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Закрити');
    }

    public function getHeader(): ?View
    {
        return view('filament.resources.lead-resource.pages.lead-status-bar', [
            'record' => $this->getRecord(),
            'stageOptions' => Lead::stageOptions(),
            'readOnly' => $this->isReadOnly(),
            'createMeasurementAction' => $this->createMeasurementAction(),
            'createOrderAction' => $this->createOrderAction(),
            'createReminderAction' => $this->createReminderAction(),
            'stagesInstructionAction' => $this->stagesInstructionAction(),
            'acceptLeadAction' => $this->acceptLeadAction(),
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
            // Client + contacts + quick actions: the same card as in the order page.
            Section::make('Клієнт')
                ->icon('heroicon-o-user')
                ->compact()
                ->extraAttributes(['class' => 'ig-card'])
                ->schema([
                    SchemaView::make('filament.resources.order-resource.components.client-card')
                        ->viewData(fn (?Lead $record): array => [
                            'client' => $record?->client,
                            'clientUrl' => $record?->client && \App\Filament\Resources\ClientResource::canEdit($record->client)
                                ? \App\Filament\Resources\ClientResource::getUrl('edit', ['record' => $record->client_id])
                                : null,
                            'showObjectAddress' => true,
                            'objectAddress' => $record?->site_address,
                            'autoAddress' => \App\Filament\Resources\LeadResource::canSeeManagers() ? $record?->auto_address : null,
                            'canEditClient' => ! $this->isReadOnly(),
                        ]),
                ]),

            Section::make('Заявка')
                ->icon('heroicon-o-inbox-arrow-down')
                ->compact()
                ->extraAttributes(['class' => 'ig-card'])
                ->columns(1)
                ->schema([
                    // Short selects in two columns, labels above the fields.
                    Grid::make(['default' => 1, '@3xl' => 2])->gridContainer()->schema([
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
                    ]),

                    Textarea::make('lost_reason')
                        ->label('Причина втрати')
                        ->lazy()
                        ->visible(fn (Get $get): bool => $get('status') === 'lost'),

                    Textarea::make('comment')
                        ->label('Коментар / + тип клієнта')
                        ->placeholder('вкажіть тип клієнта, які цінності його цікавлять')
                        ->rows(3)
                        ->lazy(),

                    Textarea::make('comment_sales_head')
                        ->label('Коментар керівника відділу продажу')
                        ->placeholder('Звернути увагу! Бачать усі, пише лише керівник')
                        ->rows(3)
                        ->lazy()
                        ->disabled(fn (): bool => ! $this->canEditSalesHeadComment()),

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
                        ->schema([
                            TextInput::make('advance_calculation2')->label('Розрахунок №2')->lazy(),
                            TextInput::make('advance_calculation3')->label('Розрахунок №3')->lazy(),
                        ]),
                ]),
        ]);
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

        if ($this->isReadOnly()) {
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
        // View-only roles may click the status bar, but nothing is saved.
        if ($this->isReadOnly()) {
            \Filament\Notifications\Notification::make()
                ->title('Лише перегляд')
                ->body('У вас немає права змінювати етап — зміна не збережена.')
                ->warning()
                ->send();

            return;
        }

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
                    ->label('Замірник')
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

                $measurement = $record->measurements()->create($data);
                // Journal of steps: «замір призначено» happened; the stage moves forward only (docs/STAGES.md).
                \App\Services\Leads\ProcessSteps::reach($record, 'measurement_scheduled', 'auto', auth()->id());

                // Automatic reminders for the lead's manager (tab «Заміри» on «Нагадування»).
                app(\App\Services\Reminders\MeasurementReminders::class)->create($measurement);

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
                    // «Потрібен» is derived from the people: nobody assigned yet = not needed (see EditOrder).
                    'is_need_measuring' => filled($measurement?->surveyor_id),
                    'is_need_install' => filled($measurement?->installer_id),
                    'address' => $record->site_address ?? $record->client?->full_address ?? '',
                    'stage' => 'new',
                    'status' => 'open',
                ]);

                \App\Services\Leads\ProcessSteps::reach($record, 'closing', 'auto', auth()->id(), 'Створено замовлення №'.$order->id);

                // Order journal: it starts as «Нове замовлення»; if a measurement was already planned, that step is recorded too.
                \App\Services\Leads\ProcessSteps::reach($order, 'new', 'auto', auth()->id());
                if ($measurement) {
                    \App\Services\Leads\ProcessSteps::reach($order, 'measuring', 'auto', auth()->id(), 'Замір призначено в заявці');
                }

                $this->fillForm();

                return $this->redirect(OrderResource::getUrl('edit', ['record' => $order]));
            });
    }
}
