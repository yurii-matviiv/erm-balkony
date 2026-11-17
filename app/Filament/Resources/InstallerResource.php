<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InstallerResource\Pages\EditInstaller;
use App\Filament\Resources\InstallerResource\Pages\ViewInstaller;
use App\Filament\Resources\InstallerResource\RelationManagers\OrdersRelationManager;
use App\Models\User;
use App\Support\RolePanels;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read-only «Картка монтажника» (old CRM: /installer/{id}). Installers are plain Users;
 * the real user admin (UserResource) is admin-only, so this separate view-only resource is
 * what the «Монтажник» cell of the orders table opens. It is not in the menu.
 *
 * Who sees whom: only users that are an installer of at least one order; a manager only
 * installers of his own orders. Authorization is explicit (not via UserPolicy, which is admin-only).
 */
class InstallerResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'installers';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $modelLabel = 'монтажник';

    protected static ?string $pluralModelLabel = 'монтажники';

    protected static bool $shouldRegisterNavigation = false;

    public static function canViewAny(): bool
    {
        return auth()->check();
    }

    public static function canView($record): bool
    {
        return auth()->check();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * The ONLY roles that may edit the installer card (everyone else — incl. Менеджер — is denied).
     * `super_admin` is the technical developer account (it is not a business role).
     */
    public const EDIT_ROLES = ['Адмін', 'Керівник відділу продажу', 'Керівник компанії', 'super_admin'];

    /**
     * Full card + editing (incl. «Активний») only for roles that oversee managers
     * (Адмін, Керівник відділу продажу, Керівник компанії, super_admin). A manager gets the
     * short read-only card.
     */
    public static function canEdit($record): bool
    {
        return in_array(auth()->user()?->getActiveRoleName(), static::EDIT_ROLES, true);
    }

    /** Where the «Монтажник» cell leads: the editable card for privileged roles, else the read-only one. */
    public static function cardUrl(int $id): string
    {
        return static::canEdit(null)
            ? static::getUrl('edit', ['record' => $id])
            : static::getUrl('view', ['record' => $id]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                TextInput::make('name')->label('ПІБ')->required()->maxLength(100),
                Toggle::make('is_active')->label('Активний')->inline(false),

                TextInput::make('phone')->label('Телефон')->tel()->maxLength(20),
                TextInput::make('additional_phone')->label('Додатковий телефон')->tel()->maxLength(20),

                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Toggle::make('viber_is')->label('Є Viber')->inline(false),

                Select::make('district_kyiv')
                    ->label('Район Києва')
                    ->options(array_combine(static::DISTRICTS, static::DISTRICTS))
                    ->placeholder('Не вказано'),
                TextInput::make('user_percent')->label('Відсоток, %')->numeric()->minValue(0)->maxValue(100),

                Textarea::make('registration_address')->label('Адреса реєстрації')->rows(2),
                Textarea::make('residence_address')->label('Адреса проживання')->rows(2),

                Textarea::make('comment')->label('Коментар')->rows(3)->columnSpanFull(),
            ]),
        ]);
    }

    private const DISTRICTS = [
        'Голосіївський', 'Дарницький', 'Деснянський', 'Дніпровський', 'Оболонський',
        'Печерський', 'Подільський', 'Святошинський', "Солом'янський", 'Шевченківський',
    ];

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        $isManager = $user && RolePanels::idForUser($user) === RolePanels::MANAGER;

        return parent::getEloquentQuery()->whereHas(
            'installerOrders',
            fn ($o) => $isManager ? $o->where('orders.manager_id', $user->id) : $o,
        );
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(3)->schema([
                TextEntry::make('name')->label('ПІБ'),
                TextEntry::make('phone')->label('Телефон')->placeholder('—'),
                TextEntry::make('is_active')
                    ->label('Активний')
                    ->formatStateUsing(fn ($state): string => $state ? 'Так' : 'Ні')
                    ->badge()
                    ->color(fn ($state): string => $state ? 'success' : 'gray'),
            ]),
        ]);
    }

    public static function getRelations(): array
    {
        return [OrdersRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'view' => ViewInstaller::route('/{record}'),
            'edit' => EditInstaller::route('/{record}/edit'),
        ];
    }
}
