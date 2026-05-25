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
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        // Адмін має доступ завжди
        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->can('view_access_control');
    }

    /**
     * ---------------------------------------------------------
     * SIDEBAR NAVIGATION ACCESS
     * ---------------------------------------------------------
     */
    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        // Адмін бачить меню завжди
        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->can('view_access_control');
    }

    /**
     * ---------------------------------------------------------
     * LOAD PAGE
     * ---------------------------------------------------------
     */
    public function mount(): void
    {
        $user = auth()->user();

        abort_unless(
            $user && (
                $user->hasRole('admin')
                || $user->can('view_access_control')
            ),
            403
        );

        /**
         * ---------------------------------------------------------
         * LOAD ROLES
         * ---------------------------------------------------------
         * ADMIN НЕ ПОКАЗУЄМО В МАТРИЦІ
         */
        $this->roles = Role::query()
            ->where('name', '!=', 'admin')
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