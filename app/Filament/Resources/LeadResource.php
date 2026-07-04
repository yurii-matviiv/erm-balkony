<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LeadResource\Pages\EditLead;
use App\Filament\Resources\LeadResource\Pages\ListLeads;
use App\Models\Client;
use App\Models\Lead;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

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

    protected static ?string $navigationLabel = 'Ліди';

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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('client.full_name')
                    ->label('ПІБ')
                    ->description(fn (Lead $record): ?string => $record->client?->phone)
                    ->searchable(['client.last_name', 'client.first_name']),

                TextColumn::make('serviceTypes.name')
                    ->label('Тип звернення')
                    ->badge()
                    ->separator(','),

                TextColumn::make('source')
                    ->label('Джерело')
                    ->formatStateUsing(fn (?string $state): string => Lead::sourceOptions()[$state] ?? '—'),

                TextColumn::make('application_type')
                    ->label('Тип заявки')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'repeat' ? 'warning' : 'success')
                    ->formatStateUsing(fn (?string $state): string => Lead::applicationTypeOptions()[$state] ?? '—'),

                TextColumn::make('stage')
                    ->label('Етап')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => Lead::stageOptions()[$state] ?? '—'),

                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'won' => 'success',
                        'lost' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => Lead::statusOptions()[$state] ?? '—'),

                TextColumn::make('created_at')
                    ->label('Дата заявки')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('source')
                    ->label('Джерело')
                    ->options(Lead::sourceOptions()),

                SelectFilter::make('stage')
                    ->label('Етап')
                    ->options(Lead::stageOptions()),

                SelectFilter::make('status')
                    ->label('Статус')
                    ->options(Lead::statusOptions()),

                SelectFilter::make('serviceTypes')
                    ->label('Тип звернення')
                    ->relationship('serviceTypes', 'name'),
            ])
            // EditAction navigates to the EditLead page (registered
            // below) instead of opening a modal, because that page
            // exists — recordAction('edit') makes clicking anywhere on
            // the row do the same thing as clicking the edit button.
            ->recordAction('edit')
            ->recordActions([
                EditAction::make('edit'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLeads::route('/'),
            'edit' => EditLead::route('/{record}/edit'),
        ];
    }
}
