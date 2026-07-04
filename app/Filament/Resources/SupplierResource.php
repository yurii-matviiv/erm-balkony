<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupplierResource\Pages\ListSuppliers;
use App\Models\Supplier;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Suppliers are intentionally a "modal resource": only the `index` route is
 * registered below (no create/edit pages). Filament's CreateAction and
 * EditAction automatically fall back to a modal form when no dedicated
 * page exists for that operation, instead of navigating to a separate
 * page. This was a deliberate UX choice (per user request) — supplier
 * records are simple enough that a full page navigation felt heavy, and a
 * modal that opens/closes without a page reload is faster to work with.
 *
 * Both child collections (contacts, payment profiles) are edited inline
 * via Repeater fields with ->relationship(), so creating/editing a
 * supplier and all of its contacts/payment profiles happens in one single
 * modal submit — no separate relation manager screens.
 */
class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = 'Постачальники';

    protected static ?string $modelLabel = 'постачальник';

    protected static ?string $pluralModelLabel = 'постачальники';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Назва постачальника')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),

            Textarea::make('notes')
                ->label('Нотатки')
                ->columnSpanFull(),

            Repeater::make('contacts')
                ->relationship()
                ->label('Контакти')
                ->addActionLabel('Додати контакт')
                ->collapsible()
                ->defaultItems(0)
                ->columnSpanFull()
                ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                ->schema([
                    TextInput::make('name')
                        ->label("Ім'я")
                        ->required()
                        ->maxLength(255),

                    TextInput::make('position')
                        ->label('Посада')
                        ->maxLength(255),

                    TextInput::make('phone')
                        ->label('Телефон')
                        ->tel()
                        ->maxLength(30),

                    TextInput::make('email')
                        ->label('Email')
                        ->email()
                        ->maxLength(255),

                    TextInput::make('viber')
                        ->label('Viber')
                        ->maxLength(30),

                    Textarea::make('comment')
                        ->label('Коментар')
                        ->columnSpanFull(),
                ])
                ->columns(2),

            // One supplier can bill through several payers (different FOPs
            // / legal sub-divisions within the same company) — see the
            // migration comment on supplier_payment_profiles for why.
            Repeater::make('paymentProfiles')
                ->relationship()
                ->label('Платіжні профілі')
                ->addActionLabel('Додати платіжний профіль')
                ->collapsible()
                ->defaultItems(0)
                ->columnSpanFull()
                ->itemLabel(fn (array $state): ?string => $state['payer_name'] ?? null)
                ->schema([
                    TextInput::make('payer_name')
                        ->label('Платник (ФОП/юр. особа)')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('tax_id')
                        ->label('ЄДРПОУ/ІПН')
                        ->maxLength(50),

                    TextInput::make('bank_name')
                        ->label('Банк')
                        ->maxLength(255),

                    TextInput::make('iban')
                        ->label('IBAN')
                        ->maxLength(34),

                    TextInput::make('mfo')
                        ->label('МФО')
                        ->maxLength(10),

                    Textarea::make('comment')
                        ->label('Коментар')
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Назва')
                    ->searchable(),

                TextColumn::make('contacts_count')
                    ->label('Контактів')
                    ->counts('contacts')
                    ->badge(),

                TextColumn::make('payment_profiles_count')
                    ->label('Платіжних профілів')
                    ->counts('paymentProfiles')
                    ->badge(),

                TextColumn::make('created_at')
                    ->label('Створено')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make()
                    ->modalWidth('2xl'),
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
            // Only the list page is registered on purpose — see the class
            // docblock. Create/edit happen in modals on this same page.
            'index' => ListSuppliers::route('/'),
        ];
    }
}
