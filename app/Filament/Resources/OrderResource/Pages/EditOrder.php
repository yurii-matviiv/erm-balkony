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
                            'stageLabel'     => Order::stageOptions()[$record->stage] ?? $record->stage,
                            'stageColor'     => match ($record->status) {
                                'done'      => 'success',
                                'cancelled' => 'danger',
                                default     => 'primary',
                            },
                            'orderTypeLabel' => Order::orderTypeOptions()[$record->order_type] ?? null,
                        ])->render()
                    );
                }),

            Section::make('Дати')
                ->icon('heroicon-o-calendar-days')
                ->compact()
                ->schema([
                    Grid::make(3)->schema([
                        DatePicker::make('measurement_date')->label('Замір')->native(false)->live(),
                        DatePicker::make('readiness_date')->label('Готовність')->native(false)->live(),
                        TimePicker::make('delivery_time')->label('Час доставки')->seconds(false)->lazy(),
                    ]),

                    Grid::make(3)->schema([
                        DatePicker::make('removal_date')->label('На вивіз')->native(false)->live(),
                        Toggle::make('removal_request_sent')->label('Заявку на вивіз надіслано')->inline(false)->live(),
                        DatePicker::make('montage_date')->label('Монтаж')->native(false)->live(),
                    ]),

                    // Кнопка "+" — показується тільки якщо жодна з додаткових
                    // дат не заповнена і користувач ще не натиснув "+".
                    Placeholder::make('add_montage_btn')
                        ->hiddenLabel()
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
                    Grid::make(3)->schema([
                        DatePicker::make('montage_date_2')->label('Монтаж #2')->native(false)->live(),
                        DatePicker::make('montage_date_3')->label('Монтаж #3')->native(false)->live(),
                        DatePicker::make('montage_date_4')->label('Монтаж #4')->native(false)->live(),
                    ])->hidden(fn (): bool =>
                        ! $this->showExtraMontage
                        && ! $this->getRecord()->montage_date_2
                        && ! $this->getRecord()->montage_date_3
                        && ! $this->getRecord()->montage_date_4
                    ),

                    Grid::make(2)->schema([
                        DatePicker::make('success_date')
                            ->label('Дата завершення')
                            ->native(false)->live()
                            ->visible(fn (Get $get): bool => $get('status') === 'done'),
                        DatePicker::make('cancel_date')
                            ->label('Дата відміни')
                            ->native(false)->live()
                            ->visible(fn (Get $get): bool => $get('status') === 'cancelled'),
                    ]),
                ]),

            Section::make('Клієнт')
                ->icon('heroicon-o-user')
                ->compact()
                // Pencil button next to the heading — see
                // HasEditClientAction docblock for why this is
                // deliberately NOT role-gated (a manager with no menu
                // access to the Клієнти module should still be able to
                // fix THIS client's details from right here).
                ->headerActions($this->isReadOnly() ? [] : [$this->editClientAction()])
                // 3 columns — per explicit feedback, ПІБ/Телефон/Адреса
                // read better as one tidy row than wrapping the 3rd field
                // onto its own line (which a 2-column grid did).
                ->columns(3)
                ->schema([
                    TextEntry::make('client.full_name')->label('ПІБ'),
                    TextEntry::make('client.phone')->label('Телефон'),
                    TextEntry::make('client.full_address')->label('Адреса клієнта')->default('—'),
                ]),

            Section::make('Замовлення')
                ->icon('heroicon-o-clipboard-document-list')
                ->compact()
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('address')
                            ->label('Адреса об\'єкта')
                            ->prefixIcon('heroicon-o-map-pin')
                            ->required()
                            ->maxLength(255)
                            ->lazy(),

                        Select::make('order_type')
                            ->label('Тип замовлення')
                            ->options(Order::orderTypeOptions())
                            ->searchable()
                            ->live(),
                    ]),

                    Grid::make(2)->schema([
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

                    Grid::make(2)->schema([
                        Select::make('installer_id')
                            ->label('Монтажник')
                            ->options(fn () => User::query()->orderBy('name')->pluck('name', 'id'))
                            ->searchable()->live(),

                        Select::make('surveyor_id')
                            ->label('Замірник (відповідальний)')
                            ->helperText('Відповідальний за бригаду — навіть якщо монтажник інший.')
                            ->options(fn () => User::query()->orderBy('name')->pluck('name', 'id'))
                            ->searchable()->live(),
                    ]),

                    // Deliberately NO supplier_id field here — per explicit
                    // feedback, a single "постачальник" picker on the Order
                    // doesn't match reality: an order can involve SEVERAL
                    // suppliers, and which one matters is really decided by
                    // each individual "Рахунок від постачальника" (supplier
                    // invoice), not the order as a whole. Properly modelling
                    // that means a supplier comes from a future per-invoice
                    // relation in the Рахунки/Оплати module, not a flat
                    // field here — see CLAUDE.md "Замовлення" for the full
                    // reasoning. The `supplier_id` column/relation on Order
                    // itself is NOT removed — historical synced orders from
                    // the old system still have it, kept for reference.
                    Grid::make(2)->schema([
                        TextInput::make('square_meters')
                            ->label('Квадратні метри')
                            ->suffix('м²')
                            ->numeric()->lazy(),

                        Toggle::make('is_need_measuring')->label('Потрібен замір')->inline(false)->live(),
                    ]),

                    Grid::make(3)->schema([
                        Select::make('stage')
                            ->label('Етап')
                            ->options(Order::stageOptions())
                            ->required()->live(),

                        Select::make('status')
                            ->label('Статус')
                            ->options(Order::statusOptions())
                            ->required()
                            ->live(),

                        Toggle::make('is_need_install')->label('Потрібен монтаж')->inline(false)->live(),
                    ]),
                ]),

            Section::make('Вартість')
                ->icon('heroicon-o-banknotes')
                ->compact()
                ->columns(3)
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
                        ->helperText('Загальна сума − фактично отримані від клієнта оплати.'),
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
                        ->helperText('Стара назва — постачальник герметика/піни "Газда".')
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
