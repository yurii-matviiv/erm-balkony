<?php

namespace App\Filament\Resources;

use App\Models\Expense;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * CRUD for the `expenses` table — general company expenses not tied to a
 * specific order: office costs, salary, taxes, marketing, telephone, etc.
 *
 * Modal resource (only `index` in getPages()) — create/edit opens as a
 * modal overlay, no separate pages needed for such simple entries.
 *
 * The sub_category select is reactive: it updates its options whenever
 * the parent `category` field changes (->live() on category Select).
 */
class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Витрати';

    protected static ?string $modelLabel = 'витрата';

    protected static ?string $pluralModelLabel = 'витрати';

    protected static string|\UnitEnum|null $navigationGroup = 'Фінанси';

    protected static ?int $navigationSort = 2;

    // ──────────────────────────────────────────────
    // Form
    // ──────────────────────────────────────────────

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('category')
                ->label('Категорія')
                ->options(Expense::categoryOptions())
                ->required()
                ->live()           // triggers sub_category options refresh
                ->afterStateUpdated(fn (callable $set) => $set('sub_category', null)),

            Select::make('sub_category')
                ->label('Підкатегорія')
                ->options(fn (Get $get): array => Expense::subCategoryOptions()[$get('category')] ?? [])
                ->placeholder('Оберіть підкатегорію')
                ->nullable(),

            Select::make('direction')
                ->label('Напрямок')
                ->options(Expense::directionOptions())
                ->default('outgo')
                ->required(),

            Select::make('payment_method')
                ->label('Метод оплати')
                ->options(Expense::paymentMethodOptions())
                ->default('cash')
                ->required(),

            TextInput::make('amount')
                ->label('Сума')
                ->numeric()
                ->suffix('₴')
                ->minValue(0)
                ->required(),

            Select::make('status')
                ->label('Статус')
                ->options(Expense::statusOptions())
                ->default('received')
                ->required(),

            DatePicker::make('paid_at')
                ->label('Дата')
                ->default(today())
                ->required(),

            Textarea::make('comment')
                ->label('Коментар')
                ->rows(2)
                ->nullable(),
        ]);
    }

    // ──────────────────────────────────────────────
    // Table
    // ──────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('paid_at')
                    ->label('Дата')
                    ->date('d.m.Y')
                    ->sortable(),

                TextColumn::make('category')
                    ->label('Категорія')
                    ->formatStateUsing(fn ($state) => Expense::categoryOptions()[$state] ?? $state)
                    ->badge()
                    ->sortable(),

                TextColumn::make('sub_category')
                    ->label('Підкатегорія')
                    ->formatStateUsing(function ($state, $record) {
                        return Expense::subCategoryOptions()[$record->category][$state] ?? ($state ?? '—');
                    }),

                TextColumn::make('amount')
                    ->label('Сума')
                    ->money('UAH', locale: 'uk')
                    ->sortable()
                    ->alignRight(),

                TextColumn::make('payment_method')
                    ->label('Метод')
                    ->formatStateUsing(fn ($state) => Expense::paymentMethodOptions()[$state] ?? $state)
                    ->badge()
                    ->color(fn ($state) => $state === 'cash' ? 'gray' : 'indigo'),

                TextColumn::make('status')
                    ->label('Статус')
                    ->formatStateUsing(fn ($state) => Expense::statusOptions()[$state] ?? $state)
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'received' => 'success',
                        'pending'  => 'warning',
                        default    => 'gray',
                    }),

                TextColumn::make('comment')
                    ->label('Коментар')
                    ->limit(40)
                    ->tooltip(fn ($state) => strlen($state) > 40 ? $state : null),
            ])
            ->defaultSort('paid_at', 'desc')
            ->filters([
                SelectFilter::make('category')
                    ->label('Категорія')
                    ->options(Expense::categoryOptions()),

                SelectFilter::make('payment_method')
                    ->label('Метод')
                    ->options(Expense::paymentMethodOptions()),

                SelectFilter::make('status')
                    ->label('Статус')
                    ->options(Expense::statusOptions()),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    // ──────────────────────────────────────────────
    // Pages — modal resource (no separate create/edit pages)
    // ──────────────────────────────────────────────

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\ExpenseResource\Pages\ListExpenses::route('/'),
        ];
    }
}
