<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VacancyResource\Pages\ListVacancies;
use App\Models\Vacancy;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Open positions ("вакансії"). A modal resource, same reasoning as
 * SupplierResource: this list is short and simple enough that a full page
 * navigation for create/edit would be overkill — see that class's docblock
 * for the general pattern (only `index` registered in getPages()).
 */
class VacancyResource extends Resource
{
    protected static ?string $model = Vacancy::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-briefcase';

    protected static string|\UnitEnum|null $navigationGroup = 'Найм';

    protected static ?string $navigationLabel = 'Вакансії';

    protected static ?string $modelLabel = 'вакансія';

    protected static ?string $pluralModelLabel = 'вакансії';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Назва вакансії')
                ->required()
                ->maxLength(255),

            TextInput::make('slug')
                ->label('Слаг для публічної форми (реклама)')
                ->helperText('Латиниця, цифри, дефіси. Використовується у посиланні для реклами: '.url('/vacancy').'/{слаг}. Якщо порожньо — публічної форми для цієї вакансії немає.')
                ->alphaDash()
                ->maxLength(255)
                ->unique(ignoreRecord: true)
                ->nullable(),

            Toggle::make('is_active')
                ->label('Відкрита для заявок')
                ->helperText('Якщо вимкнено — публічна форма (за наявності слага) показує повідомлення "набір призупинено" замість форми.')
                ->default(true),

            Textarea::make('comment')
                ->label('Коментар')
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Назва')
                    ->searchable(),

                IconColumn::make('is_active')
                    ->label('Відкрита')
                    ->boolean(),

                TextColumn::make('public_form_url')
                    ->label('Посилання на форму')
                    ->placeholder('Слаг не задано')
                    ->copyable()
                    ->copyMessage('Посилання скопійовано')
                    ->limit(40)
                    ->toggleable(),

                TextColumn::make('applications_count')
                    ->label('Заявок')
                    ->counts('applications')
                    ->badge(),

                TextColumn::make('form_views_count')
                    ->label('Переглядів форми')
                    ->counts('formViews')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Створено')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVacancies::route('/'),
        ];
    }
}
