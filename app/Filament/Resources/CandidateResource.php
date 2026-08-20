<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CandidateResource\Pages\ListCandidates;
use App\Models\Candidate;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * "Кандидати" — standalone list of everyone who ever applied for a vacancy
 * (via the public form or entered manually). Modal resource, same pattern
 * as Vacancy/VacancyApplication.
 *
 * Exists mainly to give a place to DELETE a candidate — until now Candidate
 * had no resource of its own, only the searchable Select inside
 * VacancyApplicationResource (find-or-create, never delete). Deletion is
 * default-deny like everywhere else in the project (`shield:generate` must
 * run after this resource is added, and `Delete:Candidate`/
 * `DeleteAny:Candidate` then granted only to the role(s) that should have
 * it — e.g. "Адмін" — nothing here hardcodes a role check).
 *
 * IMPORTANT: deleting a Candidate cascades — `vacancy_applications.candidate_id`
 * is `cascadeOnDelete()` (see create_vacancy_applications_table), so this
 * also deletes every VacancyApplication (and its VacancyApplicationMarketingData)
 * for that person. The delete confirmation text below says this explicitly
 * rather than relying on Filament's generic "Are you sure?".
 */
class CandidateResource extends Resource
{
    protected static ?string $model = Candidate::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|\UnitEnum|null $navigationGroup = 'Найм';

    protected static ?string $navigationLabel = 'Кандидати';

    protected static ?string $modelLabel = 'кандидат';

    protected static ?string $pluralModelLabel = 'кандидати';

    protected static ?int $navigationSort = 0;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
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
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_name')
                    ->label('ПІБ')
                    ->searchable(['last_name', 'first_name', 'middle_name']),

                TextColumn::make('phone')
                    ->label('Телефон')
                    ->searchable(),

                TextColumn::make('email')
                    ->label('Email')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('applications_count')
                    ->label('Заявок')
                    ->counts('applications')
                    ->badge(),

                TextColumn::make('created_at')
                    ->label('Створено')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),

                DeleteAction::make()
                    ->modalHeading('Видалити кандидата?')
                    ->modalDescription('Це також видалить УСІ його заявки на вакансії та історію переглядів форми. Дію неможливо скасувати.')
                    ->modalSubmitActionLabel('Так, видалити'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->modalHeading('Видалити обраних кандидатів?')
                        ->modalDescription('Це також видалить УСІ їхні заявки на вакансії та історію переглядів форми. Дію неможливо скасувати.')
                        ->modalSubmitActionLabel('Так, видалити'),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCandidates::route('/'),
        ];
    }
}
