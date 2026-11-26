<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Concerns\HasEditClientAction;
use App\Filament\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderFile;
use App\Models\User;
use App\Services\GoogleDriveService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Dedicated edit page (no modal) — same reasoning as EditLead: an Order
 * carries far too much data (crew, pricing, up to 4 installation dates,
 * ...) for a modal. No create page exists (see OrderResource).
 *
 * Field set/grouping is based directly on reading the OLD system's order
 * view template (app/Views/Order/order.php + blocks-of-orders/*.php in
 * dev.ERM-btv — read-only reference, never edited — plus a screenshot of
 * the live old CRM the user shared) to see what a manager actually looked
 * at day to day. The OLD layout reads "friendlier" because it groups
 * short related fields tightly (address+client side by side with icons,
 * 3-4 dates per row, ...) instead of one column-of-labels-then-value per
 * row — getHeader() and the nested Grid groupings in form() below borrow
 * that density without copying the old visual style 1:1 (per explicit
 * request: "не потрібно один в один").
 *
 * Deliberately NOT carried over from the old template:
 * - Per-payment entry UI, supplier commercial offer/invoice upload blocks,
 *   Google Disk file uploads -> future Рахунки/Оплати modules, see
 *   create_orders_table migration docblock.
 * - The live JS salary-calculator (deriving montage_salary from a chosen
 *   %) -> future Зарплата module's business logic; here those columns are
 *   just plain editable numbers.
 *
 * Same crew-responsibility rule as everywhere else in this project:
 * `surveyor_id` (замірник, old `gauger_id`) is always the person
 * responsible for the job, even when `installer_id` differs.
 */
class EditOrder extends EditRecord
{
    use HasEditClientAction;

    protected static string $resource = OrderResource::class;

    /**
     * Whether to show extra montage date fields (#2, #3, #4).
     * Starts false — user clicks "+" to reveal. Automatically true
     * if the record already has any extra dates filled in.
     */
    public bool $showExtraMontage = false;

    /** No save buttons anywhere — form auto-saves on every field change. */
    protected function getFormActions(): array
    {
        return [];
    }

    /**
     * READ-ONLY access for roles that may VIEW orders but not edit them
     * (Керівник компанії — the read-only partner role). Filament's
     * EditRecord normally authorizes the page against the `update`
     * ability, which blocked such roles from opening an order at all.
     * We widen page access to `view` and compensate by disabling the
     * whole form and the auto-save below for users without Update:Order —
     * so "бачу = можу відкрити" holds without granting edit rights.
     */
    protected function authorizeAccess(): void
    {
        abort_unless(
            auth()->user()?->can('Update:Order') || auth()->user()?->can('View:Order'),
            403,
        );
    }

    public function isReadOnly(): bool
    {
        return ! (auth()->user()?->can('Update:Order') ?? false);
    }

    /**
     * Auto-save silently whenever any form field updates.
     * Livewire fires updated($name) on every property write — we filter
     * to only react to changes under the 'data.' prefix (form fields).
     * Validation errors are swallowed so the user isn't interrupted while
     * still filling in a required field.
     */
    public function updated(string $name): void
    {
        if (! str_starts_with($name, 'data.')) {
            return;
        }

        // Read-only viewers must never trigger a save, even if they
        // somehow change a field value client-side.
        if ($this->isReadOnly()) {
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

    public function addMontageDate(): void
    {
        $this->showExtraMontage = true;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * «Виставити рахунок клієнту» — reusable action used as a Section
     * headerAction inside the "Рахунки" block (in the form, not the page
     * header). Opens a modal pre-filled with the client's name; the manager
     * attaches a specification file which is uploaded to Google Drive and
     * saved in order_files as type=specification.
     */
    private function issueClientInvoiceAction(): Action
    {
        return Action::make('issueClientInvoice')
            ->label('Виставити рахунок клієнту')
            ->icon('heroicon-o-document-currency-dollar')
            ->color('primary')
            ->modalHeading('Виставити рахунок клієнту')
            ->modalWidth('md')
            ->modalSubmitActionLabel('Зберегти специфікацію')
            ->form(function (): array {
                $record = $this->getRecord();
                return [
                    // Client name — display only, not editable here
                    Placeholder::make('client_name')
                        ->label('Клієнт')
                        ->content($record->client?->full_name ?? '—'),

                    FileUpload::make('specification_file')
                        ->label('Специфікація до договору')
                        ->helperText('PDF, Excel, Word, або зображення. Максимум 20 МБ.')
                        ->disk('local')
                        ->directory('uploads/order-files/tmp')
                        ->visibility('private')
                        ->maxSize(20 * 1024)
                        ->acceptedFileTypes([
                            'application/pdf',
                            'image/jpeg', 'image/png', 'image/webp',
                            'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/msword',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        ]),
                ];
            })
            ->action(function (array $data): void {
                $record = $this->getRecord();

                // File is optional for now — the button can be used
                // even if the specification isn't ready yet.
                if (empty($data['specification_file'])) {
                    Notification::make()
                        ->title('Файл не вибрано')
                        ->warning()
                        ->send();
                    return;
                }

                $relativePath = $data['specification_file'];
                $localPath    = storage_path('app/' . $relativePath);

                try {
                    $result = app(GoogleDriveService::class)->uploadFromPath(
                        localPath:    $localPath,
                        originalName: basename($relativePath),
                        type:         'specification',
                        orderId:      $record->id,
                    );

                    $record->files()->create([
                        'type'      => 'specification',
                        'file_name' => $result['file_name'],
                        'url'       => $result['url'],
                    ]);

                    Notification::make()
                        ->title('Специфікацію збережено на Google Drive')
                        ->success()
                        ->send();
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title('Помилка завантаження')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                } finally {
                    Storage::disk('local')->delete($relativePath);
                }
            });
    }

    /**
     * Who's allowed to REASSIGN the order's manager/consultant — per
     * explicit request (extended to consultant_id after the user
     * clarified "консультант те саме"), a plain "Менеджер" should only
     * ever see who's responsible (the header bar above always shows
     * both), never change either. Only "Керівник відділу продажу" and
     * "Адмін" can. `super_admin` (the Filament Shield technical role, not
     * a real job title) is included so the actual system administrator
     * isn't locked out by this rule.
     *
     * Deliberately checks `getActiveRoleName()` (the role being PREVIEWED
     * via the sidebar role switcher), not `hasRole()` (the user's real,
     * underlying roles) — otherwise an admin who also holds the Менеджер
     * role would still see these fields while previewing the manager's
     * view, defeating the whole point of that preview feature.
     */
    protected function canReassignOwnership(): bool
    {
        return in_array(
            auth()->user()?->getActiveRoleName(),
            ['Адмін', 'Керівник відділу продажу', 'super_admin'],
            true,
        );
    }

    /** Stage -> date column shown under that stage in the status bar. */
    private function statusSteps(Order $record): array
    {
        $dateColumns = [
            'measuring' => 'measurement_date',
            'ready'     => 'readiness_date',
            'montage'   => 'montage_date',
            'success'   => 'success_date',
        ];

        $steps = [];
        foreach (Order::stageOptions() as $key => $label) {
            $value = isset($dateColumns[$key]) ? $record->{$dateColumns[$key]} : null;
            $steps[$key] = [
                'label' => $label,
                'date'  => $value ? \Illuminate\Support\Carbon::parse($value)->format('d.m.Y') : null,
            ];
        }

        return $steps;
    }

    public function setStage(string $stage): void
    {
        if ($this->isReadOnly() || ! array_key_exists($stage, Order::stageOptions())) {
            return;
        }

        $this->getRecord()->update(['stage' => $stage]);
        $this->fillForm();
    }

    /**
     * Change the order type straight from the header (as in the old CRM).
     * Saves immediately, then refills the form so the "Тип замовлення"
     * select in the Замовлення card stays in sync.
     */
    /**
     * «Потрібен монтаж / замір» is derived from the pickers: a person chosen = needed, «Не призначено» =
     * not needed. Recomputed ONLY for the picker the manager actually changed, so historical orders
     * (where the old flags and the people do not match) are not rewritten silently on every save.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $record = $this->getRecord();

        if (array_key_exists('installer_id', $data) && (int) $data['installer_id'] !== (int) $record->installer_id) {
            $data['is_need_install'] = filled($data['installer_id']);
        }

        if (array_key_exists('surveyor_id', $data) && (int) $data['surveyor_id'] !== (int) $record->surveyor_id) {
            $data['is_need_measuring'] = filled($data['surveyor_id']);
        }

        return $data;
    }

    /**
     * Journal of steps (docs/STAGES.md): once the measurement date is filled, «Замір» happened as a step.
     * Done AFTER the save (before it the form's old «stage» value would overwrite the automatic move);
     * forward only, so an order already further along keeps its stage.
     */
    protected function afterSave(): void
    {
        $record = $this->getRecord();

        if ($record->measurement_date) {
            \App\Services\Leads\ProcessSteps::reach($record, 'measuring', 'auto', auth()->id(), 'Вказано дату заміру');
            $this->fillForm();
        }
    }

    /** «30.12.2026, 10:30»; without a time «30.12.2026, --:--» (the time is optional). */
    private function removalLabel(): string
    {
        $record = $this->getRecord();

        if (! $record->removal_date) {
            return '';
        }

        // No time set -> show an empty-time marker so it is visible that hours are not set.
        $time = ', '.($record->delivery_time ? substr((string) $record->delivery_time, 0, 5) : '--:--');

        return $record->removal_date->format('d.m.Y').$time;
    }

    /** Modal for «На вивіз»: date + (optional) time, entered separately. */
    public function editRemovalAction(): Action
    {
        // Eager array (not a Closure) on purpose — see HasEditClientAction about fillForm(Closure).
        $record = $this->getRecord();

        return Action::make('editRemoval')
            ->modalHeading('На вивіз')
            ->modalSubmitActionLabel('Зберегти')
            ->modalCancelActionLabel('Скасувати')
            ->modalWidth('md')
            ->visible(fn (): bool => ! $this->isReadOnly())
            ->fillForm([
                'removal_date' => $record->removal_date?->format('Y-m-d'),
                'delivery_time' => $record->delivery_time ? substr((string) $record->delivery_time, 0, 5) : null,
            ])
            ->schema([
                DatePicker::make('removal_date')->label('Дата вивозу')->native(false)->displayFormat('d.m.Y'),
                // Optional: the time may stay empty.
                TimePicker::make('delivery_time')->label('Час (необов\'язково)')->seconds(false)->nullable(),
            ])
            ->action(function (array $data): void {
                $this->getRecord()->update([
                    'removal_date' => $data['removal_date'] ? \Illuminate\Support\Carbon::parse($data['removal_date'])->toDateString() : null,
                    'delivery_time' => ($data['delivery_time'] ?? null) ?: null,
                ]);
                $this->fillForm();
            });
    }

    /** Modal editor for the object address (clicked from the read-only field). */
    public function editAddressAction(): Action
    {
        return Action::make('editAddress')
            ->modalHeading('Адреса об\'єкта')
            ->modalSubmitActionLabel('Зберегти')
            ->modalCancelActionLabel('Скасувати')
            ->modalWidth('lg')
            ->visible(fn (): bool => ! $this->isReadOnly())
            ->fillForm(['address' => $this->getRecord()->address])
            ->schema([
                Textarea::make('address')->label('Адреса')->required()->rows(3)->maxLength(255),
            ])
            ->action(function (array $data): void {
                $this->getRecord()->update(['address' => $data['address']]);
                $this->fillForm();
            });
    }

    public function setOrderType(?string $type): void
    {
        $type = $type === '' ? null : $type;

        if ($this->isReadOnly() || ($type !== null && ! array_key_exists($type, Order::orderTypeOptions()))) {
            return;
        }

        $this->getRecord()->update(['order_type' => $type]);
        $this->fillForm();
    }

    /** Профіль ліда можна міняти з замовлення: хто редагує замовлення + активна роль «Керівник компанії». */
    public function canEditProfile(): bool
    {
        return ! $this->isReadOnly()
            || auth()->user()?->getActiveRoleName() === 'Керівник компанії';
    }

    public function setLeadProfile(?string $profile): void
    {
        $profile = $profile === '' ? null : $profile;
        $lead = $this->getRecord()->lead_id ? \App\Models\Lead::find($this->getRecord()->lead_id) : null;

        if (! $lead || ! $this->canEditProfile()
            || ($profile !== null && ! array_key_exists($profile, \App\Models\Lead::profileOptions()))) {
            return;
        }

        $lead->update(['profile' => $profile]);
        $this->fillForm();
    }

    /** @return array<string, mixed> */
    protected function leadProfileViewData(): array
    {
        $lead = $this->getRecord()->lead_id ? \App\Models\Lead::find($this->getRecord()->lead_id) : null;
        $options = \App\Models\Lead::profileOptions();
        $groups = ['REHAU' => [], 'Salamander' => [], 'OpenTeck' => [], 'Українські профілі' => [], '' => []];
        foreach ($options as $key => $label) {
            $g = match (true) {
                str_starts_with($key, 'rehau') => 'REHAU',
                str_starts_with($key, 'salamander') => 'Salamander',
                str_starts_with($key, 'openteck') => 'OpenTeck',
                str_starts_with($key, 'ukr') => 'Українські профілі',
                default => '',
            };
            $groups[$g][$key] = $label;
        }

        return [
            'hasLead'         => (bool) $lead,
            'leadProfile'     => $lead?->profile,
            'profileLabel'    => $lead ? ($options[$lead->profile] ?? null) : null,
            'profileGroups'   => array_filter($groups),
            'canEditProfile'  => $this->canEditProfile(),
        ];
    }

    /**
     * Show a meaningful title in the Filament header bar (where the action
     * buttons also appear). Previously getHeader() replaced the entire
     * header widget which hid the action buttons — now we use getTitle()
     * + a form Placeholder for the summary bar instead.
     */
    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return 'Замовлення №' . $this->getRecord()->id;
    }

    /** Заголовок: «Замовлення №…» + позначка «Є відновлення», якщо лід замовлення по програмі. */
    public function getHeading(): string|\Illuminate\Contracts\Support\Htmlable
    {
        $title = e($this->getTitle());
        $lead = $this->getRecord()->lead_id ? \App\Models\Lead::find($this->getRecord()->lead_id) : null;

        if ($lead && (int) $lead->is_restoration_program === 1) {
            $title .= ' <span style="display:inline-block;vertical-align:middle;margin-left:.75rem;padding:.15rem .7rem;border-radius:9999px;background:#dcfce7;color:#166534;font-size:.9rem;font-weight:600;">Є відновлення</span>';
        }

        return new \Illuminate\Support\HtmlString($title);
    }

    /**
     * ->columns(1) is load-bearing, not decoration — see EditLead::form()
     * docblock for why (EditRecord::defaultForm() forces columns(2) on any
     * schema that doesn't set its own, which would pair these Sections up
     * two-per-row instead of stacking).
     */
    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            // Read-only mode for view-permission-only roles — see
            // authorizeAccess() above. Disables every input; actions
            // inside sections check isReadOnly() themselves.
            ->disabled($this->isReadOnly())
            ->components([

            // ── Order summary bar ──────────────────────────────────────────
            // Replaces the old getHeader() approach (which hid the Filament
            // action buttons). Renders the same blade view as before, just
            // as a Placeholder at the top of the form instead.
            Placeholder::make('order_summary')
                ->hiddenLabel()
                ->content(function (): \Illuminate\Support\HtmlString {
                    $record = $this->getRecord();
                    return new \Illuminate\Support\HtmlString(
                        view('filament.resources.order-resource.pages.order-header', [
                            'record'         => $record,
                            'steps'          => $this->statusSteps($record),
                            'extra'          => $record->status === 'cancelled'
                                ? [['label' => 'Відмінено' . ($record->cancel_date ? ' · ' . \Illuminate\Support\Carbon::parse($record->cancel_date)->format('d.m.Y') : ''), 'tone' => 'danger']]
                                : [],
                            'canChange'      => ! $this->isReadOnly(),
                            'orderTypeLabel' => Order::orderTypeOptions()[$record->order_type] ?? null,
                            'orderTypeOptions' => Order::orderTypeOptions(),
                            ...$this->leadProfileViewData(),
                        ])->render()
                    );
                }),

            Section::make('Дати')
                ->icon('heroicon-o-calendar-days')
                ->compact()
                ->extraAttributes(['class' => 'ig-card'])
                ->inlineLabel()
                ->schema([
                    Grid::make(['default' => 1, '@3xl' => 2, '@6xl' => 3])->gridContainer()->schema([
                        DatePicker::make('measurement_date')->label('Замір')->native(false)->displayFormat('d.m.Y')->suffixIcon('heroicon-o-calendar-days')->live(),
                        DatePicker::make('readiness_date')->label('Готовність')->native(false)->displayFormat('d.m.Y')->suffixIcon('heroicon-o-calendar-days')->live(),
                    ]),

                    Grid::make(['default' => 1, '@3xl' => 2, '@6xl' => 3])->gridContainer()->schema([
                        // ONE compact read-only field «дата, час»; click opens a modal
                        // (editRemovalAction) with the date and the OPTIONAL time.
                        TextInput::make('removal_display')
                            ->label('На вивіз')
                            ->readOnly()
                            ->dehydrated(false)
                            ->placeholder('—')
                            ->suffixIcon('heroicon-o-calendar-days')
                            ->formatStateUsing(fn (): string => $this->removalLabel())
                            ->extraInputAttributes(fn (): array => $this->isReadOnly() ? [] : [
                                'wire:click' => "mountAction('editRemoval')",
                                'style' => 'cursor:pointer;',
                            ]),
                        DatePicker::make('montage_date')->label('Монтаж')->native(false)->displayFormat('d.m.Y')->suffixIcon('heroicon-o-calendar-days')->live(),
                    ]),

                    // Кнопка "+" — показується тільки якщо жодна з додаткових
                    // дат не заповнена і користувач ще не натиснув "+".
                    Placeholder::make('add_montage_btn')
                        ->hiddenLabel()
                        ->extraAttributes(['class' => 'ig-plain'])
                        ->content(new \Illuminate\Support\HtmlString(
                            '<button type="button" wire:click="addMontageDate"
                                class="text-sm text-primary-600 dark:text-primary-400 hover:underline cursor-pointer">
                                + Додати дату монтажу
                            </button>'
                        ))
                        ->hidden(fn (): bool =>
                            $this->showExtraMontage
                            || (bool) $this->getRecord()->montage_date_2
                            || (bool) $this->getRecord()->montage_date_3
                            || (bool) $this->getRecord()->montage_date_4
                        ),

                    // Додаткові дати — видно якщо натиснуто "+" або вже є дані.
                    Grid::make(['default' => 1, '@3xl' => 2, '@6xl' => 3])->gridContainer()->schema([
                        DatePicker::make('montage_date_2')->label('Монтаж #2')->native(false)->displayFormat('d.m.Y')->suffixIcon('heroicon-o-calendar-days')->live(),
                        DatePicker::make('montage_date_3')->label('Монтаж #3')->native(false)->displayFormat('d.m.Y')->suffixIcon('heroicon-o-calendar-days')->live(),
                        DatePicker::make('montage_date_4')->label('Монтаж #4')->native(false)->displayFormat('d.m.Y')->suffixIcon('heroicon-o-calendar-days')->live(),
                    ])->hidden(fn (): bool =>
                        ! $this->showExtraMontage
                        && ! $this->getRecord()->montage_date_2
                        && ! $this->getRecord()->montage_date_3
                        && ! $this->getRecord()->montage_date_4
                    ),

                    Grid::make(['default' => 1, '@3xl' => 2])->gridContainer()->schema([
                        DatePicker::make('success_date')
                            ->label('Дата завершення')
                            ->native(false)->displayFormat('d.m.Y')->suffixIcon('heroicon-o-calendar-days')->live()
                            ->visible(fn (Get $get): bool => $get('status') === 'done'),
                        DatePicker::make('cancel_date')
                            ->label('Дата відміни')
                            ->native(false)->displayFormat('d.m.Y')->suffixIcon('heroicon-o-calendar-days')->live()
                            ->visible(fn (Get $get): bool => $get('status') === 'cancelled'),
                    ]),

                    // Наприкінці секції, на всю ширину рядка: це позначка
                    // («надіслано» / ні), тому чекбокс, а не перемикач.
                    Checkbox::make('removal_request_sent')
                        ->label('Заявку на вивіз надіслано')
                        ->inline()
                        ->inlineLabel(false)
                        ->live(),
                ]),

            Section::make('Клієнт')
                ->icon('heroicon-o-user')
                ->compact()
                ->extraAttributes(['class' => 'ig-card'])
                // Read-only client card (avatar, name -> client card, phone/email/address).
                // No edit modal here any more: editing happens in the full client card
                // (pencil next to the name / «Повна картка клієнта»).
                ->schema([
                    \Filament\Schemas\Components\View::make('filament.resources.order-resource.components.client-card')
                        ->viewData(fn (): array => [
                            'client' => $this->getRecord()->client,
                            'clientUrl' => $this->getRecord()->client && \App\Filament\Resources\ClientResource::canEdit($this->getRecord()->client)
                                ? \App\Filament\Resources\ClientResource::getUrl('view', ['record' => $this->getRecord()->client_id])
                                : null,
                            'canEditClient' => ! $this->isReadOnly(),
                            // The lead this order was created from (old CRM: order.lead_id).
                            'leadId' => $this->getRecord()->lead_id,
                            'leadUrl' => ($lead = $this->getRecord()->lead_id ? \App\Models\Lead::find($this->getRecord()->lead_id) : null) && \App\Filament\Resources\LeadResource::canEdit($lead)
                                ? \App\Filament\Resources\LeadResource::getUrl('edit', ['record' => $lead->id])
                                : null,
                        ]),
                ]),

            Section::make('Замовлення')
                ->icon('heroicon-o-clipboard-document-list')
                ->compact()
                ->extraAttributes(['class' => 'ig-card'])
                ->inlineLabel()
                ->schema([
                    Grid::make(['default' => 1, '@3xl' => 2])->gridContainer()->schema([
                        // Read-only display; a click opens a modal (editAddressAction)
                        // so a long address is edited in a roomy textarea, not a
                        // one-line input. No icon — it covered the text.
                        TextInput::make('address')
                            ->label('Адреса об\'єкта')
                            ->readOnly()
                            ->extraInputAttributes(fn (): array => $this->isReadOnly() ? [] : [
                                'wire:click' => "mountAction('editAddress')",
                                'style' => 'cursor:pointer;text-overflow:ellipsis;',
                                'title' => $this->getRecord()->address,
                            ]),

                        Select::make('order_type')
                            ->label('Тип замовлення')
                            ->options(Order::orderTypeOptions())
                            ->searchable()
                            ->live(),

                        // Постачальник вікон — з довідника наших постачальників.
                        // Пише в orders.supplier_id (у старій CRM те саме поле, синхронізується
                        // через suppliers.legacy_id, див. OrdersSyncMapper).
                        Select::make('supplier_id')
                            ->label('Постачальник вікон')
                            ->placeholder('Не вибрано')
                            ->options(fn () => \App\Models\Supplier::query()->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->live(),
                    ]),

                    Grid::make(['default' => 1, '@3xl' => 2])->gridContainer()->schema([
                        // Hidden (not just disabled) for a plain "Менеджер"
                        // — per explicit request, a manager should only
                        // ever SEE who's responsible (in the header bar
                        // above), never reassign it themselves. Checked
                        // against the ACTIVE role (see canReassignOwnership()
                        // docblock), so an admin previewing the manager's
                        // view via the role switcher sees exactly this too.
                        Select::make('manager_id')
                            ->label('Менеджер')
                            ->prefixIcon('heroicon-o-user')
                            ->options(fn () => User::query()->orderBy('name')->pluck('name', 'id'))
                            ->searchable()->live()
                            ->hidden(fn (): bool => ! $this->canReassignOwnership()),

                        Select::make('consultant_id')
                            ->label('Консультант')
                            ->helperText('Якщо двоє ведуть одне замовлення.')
                            ->options(fn () => User::query()->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->hidden(),
                    ]),

                    Grid::make(['default' => 1, '@3xl' => 2])->gridContainer()->schema([
                        Select::make('installer_id')
                            ->label('Монтажник')
                            ->placeholder('Не призначено')
                            ->options(fn () => User::query()->orderBy('name')->pluck('name', 'id'))
                            ->searchable()->live(),

                        Select::make('surveyor_id')
                            ->label('Замірник')
                            ->placeholder('Не призначено')
                            ->options(fn () => User::query()->orderBy('name')->pluck('name', 'id'))
                            ->searchable()->live(),
                    ]),

                    // «Постачальник вікон» (supplier_id) — вибір у секції «Замовлення» вище (з 26.11.2026).
                    // Постачальники окремих рахунків — це окрема майбутня зв'язка в модулі Рахунки/Оплати.
                    // No «Потрібен замір / монтаж» checkboxes any more: the flags are DERIVED from the
                    // pickers above (a person chosen = needed, «Не призначено» = not needed) and still
                    // stored in is_need_measuring / is_need_install — see mutateFormDataBeforeSave().

                    Grid::make(['default' => 1, '@3xl' => 2])->gridContainer()->schema([
                        Select::make('stage')
                            ->label('Етап')
                            ->options(Order::stageOptions())
                            ->required()->live(),

                        Select::make('status')
                            ->label('Статус')
                            ->options(Order::statusOptions())
                            ->required()
                            ->live(),
                    ]),

                    Grid::make(['default' => 1, '@3xl' => 2])->gridContainer()->schema([
                        TextInput::make('square_meters')
                            ->label('Квадратні метри')
                            ->suffix('м²')
                            ->numeric()->lazy(),
                    ]),
                ]),

            Section::make('Вартість')
                ->icon('heroicon-o-banknotes')
                ->compact()
                ->extraAttributes(['class' => 'ig-card'])
                ->inlineLabel()
                ->columns(['default' => 1, '@3xl' => 2, '@6xl' => 3])->gridContainer()
                ->schema([
                    TextInput::make('total_price')->label('Загальна сума')->numeric()->suffix('грн')->lazy(),
                    // Computed — не зберігається, розраховується щоразу:
                    // total_price мінус фактично отримані від клієнта оплати.
                    // Стовпець balance в БД залишається для сумісності зі
                    // старими синхронізованими даними, але в інтерфейсі
                    // більше не редагується вручну.
                    TextEntry::make('client_balance_due')
                        ->label('Залишок до сплати (клієнт)')
                        ->suffix(' грн')
                        ->state(function (Order $record): string {
                            $paid = $record->payments()
                                ->where('direction', 'income')
                                ->where('payer_type', 'client')
                                ->sum('amount');
                            $due  = max(0, (float) ($record->total_price ?? 0) - (float) $paid);

                            return number_format($due, 0, '', ' ');
                        })
                        ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Загальна сума − фактично отримані від клієнта оплати.'),
                    TextInput::make('discount')->label('Знижка')->numeric()->suffix('%')->lazy(),
                    TextInput::make('montage_price_m2')->label('Вартість монтажу за м²')->numeric()->suffix('грн')->lazy(),
                    TextInput::make('montage_price')->label('Вартість монтажу')->numeric()->suffix('грн')->lazy(),
                    TextInput::make('montage_salary')->label('Нараховано монтажнику')->numeric()->suffix('грн')->lazy(),
                    TextInput::make('measuring_price')->label('Вартість заміру')->numeric()->suffix('грн')->lazy(),
                    TextInput::make('additional_price')->label('Доп. роботи')->numeric()->suffix('грн')->lazy(),
                    TextInput::make('additional_salary')->label('Нараховано за доп. роботи')->numeric()->suffix('грн')->lazy(),
                    TextInput::make('cost_of_lifts')->label('Вартість підйомів')->numeric()->suffix('грн')->lazy(),
                    TextInput::make('gazda_price')
                        ->label('Газда')
                        ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Стара назва — постачальник герметика/піни "Газда".')
                        ->numeric()->suffix('грн')->lazy(),
                ]),

            Section::make('Коментарі')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->compact()
                ->columns(2)
                ->schema([
                    Textarea::make('comment')->label('Коментар менеджера')->lazy(),

                    Textarea::make('cancel_reason')
                        ->label('Причина відмови')
                        ->lazy()
                        ->visible(fn (Get $get): bool => $get('status') === 'cancelled'),

                    Textarea::make('client_feedback')
                        ->label('Відгук клієнта')
                        ->lazy()
                        ->visible(fn (Get $get): bool => $get('status') === 'done'),
                ]),

            // ── Рахунки ───────────────────────────────────────────────────
            // Future module: issued client invoices + supplier purchases.
            // Button "Виставити рахунок клієнту" lives here so the
            // manager creates a record and immediately sees it in this block.
            // Файли (attached Drive documents) appear BELOW, after this block.
            // ── Дії ───────────────────────────────────────────────────────
            // Primary workflow buttons for this order, in the order a manager
            // typically performs them:
            //   1. Створити договір        — contract (stub, not implemented yet)
            //   2. Виставити рахунок клієнту — issues a spec + client invoice
            //   3. Рахунок від постачальника — supplier invoice upload (stub)
            Section::make('Дії')
                ->icon('heroicon-o-bolt')
                ->compact()
                ->schema([
                    \Filament\Schemas\Components\Actions::make([

                        Action::make('createContract')
                            ->label('Створити договір')
                            ->icon('heroicon-o-document-text')
                            ->color('gray')
                            ->action(fn () => Notification::make()
                                ->title('Функціонал в розробці')
                                ->info()
                                ->send()),

                        $this->issueClientInvoiceAction(),

                        Action::make('supplierInvoice')
                            ->label('Рахунок від постачальника')
                            ->icon('heroicon-o-truck')
                            ->color('gray')
                            ->action(fn () => Notification::make()
                                ->title('Функціонал в розробці')
                                ->info()
                                ->send()),

                    ])->fullWidth(false),
                ]),

            // ── Рахунки ───────────────────────────────────────────────────
            // Future: issued client invoices + supplier purchases table.
            Section::make('Рахунки')
                ->icon('heroicon-o-document-currency-dollar')
                ->compact()
                ->collapsible()
                ->schema([
                    Placeholder::make('invoices_empty')
                        ->hiddenLabel()
                        ->content(new \Illuminate\Support\HtmlString(
                            '<p class="text-sm text-gray-400 dark:text-gray-500 py-1">'
                            . 'Рахунки ще не виставлені.'
                            . '</p>'
                        )),
                ]),

            // Historical payments synced from old CRM — read-only display.
            // Rendered via a Blade view (simpler than a RepeatableEntry for
            // cross-cutting summary totals + table in one block).
            // New payment entries will come from the future Рахунки module.
            Section::make('Оплати')
                ->icon('heroicon-o-banknotes')
                ->compact()
                ->collapsible()
                ->schema([
                    Placeholder::make('payments_display')
                        ->hiddenLabel()
                        ->content(function (): \Illuminate\Support\HtmlString {
                            $record = $this->getRecord();

                            // Split salary rows out of the main table — old system
                            // also excluded them from the order payment block
                            // (see OrderPaymentsSyncMapper docblock). They're shown
                            // in a collapsed <details> below the main table instead.
                            $mainPayments   = $record->payments()->where(function ($q) {
                                $q->where('category', '!=', 'salary')->orWhereNull('category');
                            })->get();
                            $salaryPayments = $record->payments()->where('category', 'salary')->get();

                            return new \Illuminate\Support\HtmlString(
                                view('filament.resources.order-resource.pages.order-payments', [
                                    'mainPayments'   => $mainPayments,
                                    'salaryPayments' => $salaryPayments,
                                ])->render()
                            );
                        }),
                ]),

            // ── Файли ─────────────────────────────────────────────────────
            // Google Drive file links synced from old CRM + uploaded via
            // «Виставити рахунок клієнту». Kept at the very bottom — files
            // are reference material, not the primary working area.
            Section::make('Файли')
                ->icon('heroicon-o-paper-clip')
                ->compact()
                ->collapsible()
                ->schema([
                    Placeholder::make('files_display')
                        ->hiddenLabel()
                        ->content(function (): \Illuminate\Support\HtmlString {
                            $files = $this->getRecord()->files()->get();

                            return new \Illuminate\Support\HtmlString(
                                view('filament.resources.order-resource.pages.order-files', [
                                    'files' => $files,
                                ])->render()
                            );
                        }),
                ]),
        ]);
    }
}
