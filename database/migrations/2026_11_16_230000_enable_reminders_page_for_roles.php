<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Enables the "Нагадування" page (App\Filament\Pages\Reminders) in the sidebar
 * of the roles that work with reminders, and grants them the matching view
 * permission — exactly what toggling the item in "Бокова панель" does
 * (SidebarPermissionSync), done once here so local and hosting stay identical.
 * Every user only ever sees THEIR OWN reminders, regardless of role.
 * Idempotent; roles that don't exist are skipped.
 */
return new class extends Migration
{
    private const ITEM = 'App\\Filament\\Pages\\Reminders';

    private const PERMISSION = 'View:Reminders';

    /** role => group_label ('' = flat menu like the old CRM, null = role's default grouping). */
    private function roles(): array
    {
        return [
            'Менеджер' => ['', 8],
            'Керівник відділу продажу' => [null, 0],
            'Керівник компанії' => [null, 0],
            'Адмін' => [null, 0],
        ];
    }

    public function up(): void
    {
        $now = now();

        $permissionId = DB::table('permissions')->where('name', self::PERMISSION)->where('guard_name', 'web')->value('id')
            ?? DB::table('permissions')->insertGetId(['name' => self::PERMISSION, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now]);

        foreach ($this->roles() as $role => [$group, $sort]) {
            $roleId = DB::table('roles')->where('name', $role)->where('guard_name', 'web')->value('id');

            if (! $roleId) {
                continue;
            }

            DB::table('navigation_settings')->updateOrInsert(
                ['role' => $role, 'item_key' => self::ITEM],
                ['group_label' => $group, 'group_sort' => 0, 'item_sort' => $sort, 'is_active' => 1, 'updated_at' => $now, 'created_at' => $now],
            );

            DB::table('role_has_permissions')->updateOrInsert(['permission_id' => $permissionId, 'role_id' => $roleId], []);
        }

        // super_admin gets every generated permission (shield:generate does it on deploy too).
        $superId = DB::table('roles')->where('name', 'super_admin')->where('guard_name', 'web')->value('id');

        if ($superId) {
            DB::table('role_has_permissions')->updateOrInsert(['permission_id' => $permissionId, 'role_id' => $superId], []);
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Intentionally empty: menu overrides are plain settings, not schema.
    }
};
