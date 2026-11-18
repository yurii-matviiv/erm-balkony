<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Filament\Resources\UserResource\Pages\ViewUser;
use App\Filament\Actions\ImpersonateAction;
use Filament\Actions\ViewAction;
use Filament\Schemas\Components\Actions;
use App\Filament\Actions\SendPasswordLinkAction;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View as SchemaView;
use App\Models\User;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Operation;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role;

/**
 * CRUD for application users: profile fields + role assignment.
 *
 * Roles themselves (their permissions) are managed on the separate "Ролі"
 * page provided by filament-shield. Here we only choose WHICH roles a
 * given user has — a user can have several at once (e.g. "Менеджер" and
 * "Фінансист"), which is what controls what they see in the rest of the
 * panel, once permissions are configured per role.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Користувачі';

    protected static ?string $modelLabel = 'користувач';

    protected static ?string $pluralModelLabel = 'користувачі';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label("Ім'я")
                ->required()
                ->maxLength(100),

            TextInput::make('last_name')
                ->label('Прізвище')
                ->maxLength(50),

            TextInput::make('middle_name')
                ->label('По батькові')
                ->maxLength(50),

            TextInput::make('email')
                ->label('Email')
                ->email()
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(255),

            TextInput::make('phone')
                ->label('Телефон')
                ->tel()
                ->maxLength(20),

            // Only shown when creating a brand-new user. Existing (including
            // synced) users keep their current password; changing it is a
            // separate, deliberate action, not something you do by accident
            // while editing a profile.
            TextInput::make('password')
                ->label('Пароль')
                ->password()
                ->required()
                ->minLength(8)
                ->hiddenOn(Operation::Edit),

            Select::make('roles')
                ->label('Ролі')
                ->relationship('roles', 'name')
                ->multiple()
                ->preload()
                ->searchable()
                ->helperText('Користувач може мати кілька ролей одночасно. Самі ролі та їх права доступу налаштовуються на сторінці "Ролі".'),
        ]);
    }

    /**
     * Редагувати користувачів може лише адміністратор (реальна роль
     * super_admin) — це діє і для олівця в картці, і для кнопки в таблиці,
     * і для самої сторінки редагування.
     */
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return (bool) auth()->user()?->hasRole('super_admin');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            // Вкладки зверху, як у картці клієнта (вигляд — tabs-styles.blade.php).
            // «Вкладка 2» — заготовка під наступні пункти картки.
            Tabs::make('user-card')->columnSpanFull()->tabs([
                Tab::make('Контактні дані')
                    ->icon('heroicon-o-identification')
                    ->schema([
                        SchemaView::make('filament.resources.user-resource.components.user-card'),
                    ]),
                Tab::make('Вкладка 2')
                    ->icon('heroicon-o-squares-2x2')
                    ->schema([
                        SchemaView::make('filament.resources.user-resource.components.tab-placeholder')->viewData(['title' => 'Вкладка 2']),
                    ]),
                Tab::make('Акаунт')
                    ->icon('heroicon-o-key')
                    ->schema([
                        SchemaView::make('filament.resources.user-resource.components.user-account'),
                        Actions::make([SendPasswordLinkAction::make()]),
                    ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label("Ім'я")
                    ->searchable(),

                TextColumn::make('last_name')
                    ->label('Прізвище')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),

                TextColumn::make('phone')
                    ->label('Телефон')
                    ->toggleable(),

                TextColumn::make('roles.name')
                    ->label('Ролі')
                    ->badge()
                    ->separator(','),

                TextColumn::make('legacy_id')
                    ->label('Зі старої БД')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Створено')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // За замовчуванням — лише активні; «Усі» показує й неактивних.
                SelectFilter::make('is_active')
                    ->label('Статус')
                    ->options([
                        '1' => 'Активні',
                        '0' => 'Неактивні',
                    ])
                    ->default('1')
                    ->native(false),

                SelectFilter::make('roles')
                    ->label('Роль')
                    ->relationship('roles', 'name'),
            ])
            ->recordActions([
                ViewAction::make(),
                ImpersonateAction::make(),
                EditAction::make(),
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
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'view' => ViewUser::route('/{record}'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
