<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VacancyApplicationResource\Pages\ListVacancyApplications;
use App\Models\Candidate;
use App\Models\VacancyApplication;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * "Заявки на вакансії" — one application from one candidate for one
 * vacancy. Modal resource, same reasoning as Supplier/Vacancy.
 *
 * The trickiest requirement here was the candidate phone-number lookup: an
 * application's candidate is picked via a searchable Select (search by
 * phone or name) instead of typing contact fields directly into this form.
 * If a matching candidate already exists, the person creating the
 * application just picks them from the dropdown ("додати йому ще одну
 * заявку"). If not, the "+" button on the select opens a small inline form
 * to create a brand-new Candidate ("створити новий контакт") without
 * leaving this modal. This is Filament's standard relationship-select +
 * createOptionForm pattern, which maps directly onto the "check if a
 * contact with this phone already exists" flow that was asked for, without
 * needing any custom duplicate-detection JS/logic.
 */
class VacancyApplicationResource extends Resource
{
    protected static ?string $model = VacancyApplication::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-plus';

    protected static string|\UnitEnum|null $navigationGroup = 'Найм';

    protected static ?string $navigationLabel = 'Заявки на вакансії';

    protected static ?string $modelLabel = 'заявка';

    protected static ?string $pluralModelLabel = 'заявки';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('candidate_id')
                ->label('Кандидат (телефон або ПІБ)')
                ->relationship('candidate', 'phone')
                ->searchable(['phone', 'last_name', 'first_name'])
                ->getOptionLabelFromRecordUsing(fn (Candidate $record): string => "{$record->full_name} — {$record->phone}")
                ->preload()
                ->required()
                ->createOptionForm([
                    TextInput::make('phone')
                        ->label('Телефон')
                        ->tel()
                        ->required()
                        ->maxLength(30),

                    TextInput::make('last_name')
                        ->label('Прізвище')
                        ->maxLength(255),

                    TextInput::make('first_name')
                        ->label("Ім'я")
                        ->maxLength(255),

                    TextInput::make('middle_name')
                        ->label('По батькові')
                        ->maxLength(255),

                    TextInput::make('email')
                        ->label('Email')
                        ->email()
                        ->maxLength(255),
                ])
                ->createOptionModalHeading('Новий кандидат')
                ->helperText('Почніть вводити телефон або ПІБ. Якщо такий кандидат вже є — оберіть його (це буде ще одна заявка для цієї людини). Якщо немає — натисніть "+", щоб створити новий контакт.'),

            Select::make('vacancy_id')
                ->label('Вакансія')
                ->relationship('vacancy', 'name')
                ->required()
                ->preload(),

            Select::make('advertising_channel')
                ->label('Звідки прийшов')
                ->options(VacancyApplication::channelOptions()),

            Toggle::make('is_targeted')
                ->label('Цільова заявка')
                ->helperText('Чи людина свідомо подавалась саме на цю вакансію, а не випадковий/загальний контакт.'),

            Textarea::make('comment')
                ->label('Коментар')
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('candidate.full_name')
                    ->label('ПІБ')
                    ->searchable(['last_name', 'first_name', 'middle_name'])
                    ->description(fn (VacancyApplication $record): ?string => $record->candidate?->phone),

                TextColumn::make('vacancy.name')
                    ->label('Вакансія')
                    ->badge(),

                IconColumn::make('is_targeted')
                    ->label('Цільова')
                    ->boolean(),

                TextColumn::make('advertising_channel')
                    ->label('Канал')
                    ->formatStateUsing(fn (?string $state): string => VacancyApplication::channelOptions()[$state] ?? '—')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Дата заявки')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('vacancy_id')
                    ->label('Вакансія')
                    ->relationship('vacancy', 'name'),

                SelectFilter::make('advertising_channel')
                    ->label('Канал')
                    ->options(VacancyApplication::channelOptions()),

                TernaryFilter::make('is_targeted')
                    ->label('Цільова заявка'),
            ])
            // Clicking anywhere on the row opens the edit modal, not just
            // the action button — matches the "клацаючи на нього потрапляємо
            // в редагування" requirement.
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
            'index' => ListVacancyApplications::route('/'),
        ];
    }
}
