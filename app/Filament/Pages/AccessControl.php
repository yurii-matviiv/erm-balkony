<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AccessControl extends Page
{
    protected string $view = 'filament.pages.access-control';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Access Control';

    protected static ?string $title = 'Access Control';

    protected static ?int $navigationSort = 3;

    public array $matrix = [];

    public array $roles = [];

    public array $permissions = [];

    public array $permissionLabels = [];

    /**
     * ---------------------------------------------------------
     * PAGE ACCESS
     * ---------------------------------------------------------
     */
    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_access_control') ?? false;
    }

    /**
     * ---------------------------------------------------------
     * SIDEBAR NAVIGATION ACCESS
     * ---------------------------------------------------------
     */
    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->can('view_access_control') ?? false;
    }

    /**
     * ---------------------------------------------------------
     * LOAD PAGE
     * ---------------------------------------------------------
     */
    public function mount(): void
    {
        abort_unless(
            auth()->user()?->can('view_access_control'),
            403
        );

        /**
         * ---------------------------------------------------------
         * LOAD ROLES
         * ---------------------------------------------------------
         */
        $this->roles = Role::query()
            ->orderBy('name')
            ->pluck('name')
            ->toArray();

        /**
         * ---------------------------------------------------------
         * LOAD PERMISSIONS
         * ---------------------------------------------------------
         */
        $this->permissions = Permission::query()
            ->orderBy('name')
            ->pluck('name')
            ->toArray();

        /**
         * ---------------------------------------------------------
         * AUTO MAP FILAMENT PAGE TITLES
         * ---------------------------------------------------------
         */
        foreach (Filament::getPages() as $pageClass) {

            $permission = 'view_' . str(class_basename($pageClass))
                ->snake()
                ->lower();

            $title = $pageClass::getNavigationLabel()
                ?? class_basename($pageClass);

            $this->permissionLabels[$permission] = $title;
        }

        /**
         * ---------------------------------------------------------
         * BUILD ACCESS MATRIX
         * ---------------------------------------------------------
         */
        foreach ($this->permissions as $permission) {

            foreach ($this->roles as $role) {

                $this->matrix[$permission][$role] =
                    Role::findByName($role)
                        ->hasPermissionTo($permission);
            }
        }
    }

    /**
     * ---------------------------------------------------------
     * TOGGLE ROLE PERMISSION
     * ---------------------------------------------------------
     */
    public function togglePermission(
        string $role,
        string $permission
    ): void {

        $roleModel = Role::findByName($role);

        /**
         * ---------------------------------------------------------
         * REMOVE PERMISSION
         * ---------------------------------------------------------
         */
        if ($roleModel->hasPermissionTo($permission)) {

            $roleModel->revokePermissionTo($permission);

            app(PermissionRegistrar::class)
                ->forgetCachedPermissions();

            $this->matrix[$permission][$role] = false;

            return;
        }

        /**
         * ---------------------------------------------------------
         * ADD PERMISSION
         * ---------------------------------------------------------
         */
        $roleModel->givePermissionTo($permission);

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        $this->matrix[$permission][$role] = true;
    }
}