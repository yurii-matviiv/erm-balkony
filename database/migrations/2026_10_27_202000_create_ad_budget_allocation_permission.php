<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permission for the "Розподіл бюджету реклами" page. Created here and granted to super_admin only;
 * other roles get it through "Бокова панель".
 */
return new class extends Migration
{
    public function up(): void
    {
        $permissionId = DB::table('permissions')->where('name', 'View:AdBudgetAllocation')->where('guard_name', 'web')->value('id');

        if (! $permissionId) {
            $permissionId = DB::table('permissions')->insertGetId([
                'name' => 'View:AdBudgetAllocation',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // only super_admin gets it (other roles via "Бокова панель")
        $roleId = DB::table('roles')->where('name', 'super_admin')->where('guard_name', 'web')->value('id');

        if ($roleId) {
            DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $roleId]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::table('permissions')->where('name', 'View:AdBudgetAllocation')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
