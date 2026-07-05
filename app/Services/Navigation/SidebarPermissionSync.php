<?php

namespace App\Services\Navigation;

use Filament\Pages\Page;
use Filament\Resources\Resource;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Couples sidebar visibility to VIEW access, per explicit request:
 * enabling a menu item for a role in "Бокова панель" also grants that
 * role the permission(s) needed to actually open the page; disabling the
 * item revokes them (full symmetry — "бачу = можу відкрити", confirmed
 * by the user, including the revoke direction).
 *
 * Scope guard-rails:
 * - Only VIEW-level permissions are touched (ViewAny/View for resources,
 *   View:<PageClass> for pages). Action permissions (Create/Update/
 *   Delete/...) always stay manual, on the "Ролі" page — seeing a list
 *   and changing its rows are different-weight decisions.
 * - super_admin and founder are never synced: super_admin bypasses all
 *   checks anyway (gate-before), founder is a deliberately minimal role.
 * - Synthetic nav items (e.g. 'quick-create-lead') have no class behind
 *   them — nothing to grant, silently skipped.
 */
class SidebarPermissionSync
{
    private const EXEMPT_ROLES = ['super_admin', 'founder'];

    /**
     * Shield-style view permission names for one nav item key (a Resource
     * or Page FQCN from NavigationCatalog), [] when not applicable.
     *
     * @return list<string>
     */
    public static function viewPermissionsFor(string $itemKey): array
    {
        if (! class_exists($itemKey)) {
            return [];
        }

        if (is_subclass_of($itemKey, Resource::class)) {
            $model = class_basename($itemKey::getModel());

            // Same pascal-case "Affix:Subject" convention Shield generates
            // and our policies check (e.g. OrderPolicy -> 'View:Order').
            return ['ViewAny:'.$model, 'View:'.$model];
        }

        if (is_subclass_of($itemKey, Page::class)) {
            return ['View:'.class_basename($itemKey)];
        }

        return [];
    }

    public static function apply(string $roleName, string $itemKey, bool $active): void
    {
        if (in_array($roleName, self::EXEMPT_ROLES, true)) {
            return;
        }

        $permissionNames = self::viewPermissionsFor($itemKey);

        if ($permissionNames === []) {
            return;
        }

        $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();

        if (! $role) {
            return;
        }

        foreach ($permissionNames as $name) {
            // findOrCreate: the permission may not exist yet if
            // shield:generate hasn't run since the Resource/Page was
            // added — the generator will simply reuse this row later.
            $permission = Permission::findOrCreate($name, 'web');

            $active
                ? $role->givePermissionTo($permission)
                : $role->revokePermissionTo($permission);
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
