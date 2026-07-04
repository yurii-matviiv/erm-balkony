<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Role for the EXTERNAL marketing agency account: exactly ONE permission
 * (`View:LeadExport` — the "Експорт лідів" page + its CSV download
 * routes), nothing else. Same pattern as the `founder` role (see
 * create_page_docs_table): a purpose-built role created in a migration so
 * it exists on every environment without manual clicking, following the
 * project's "zero permissions by default" convention — except here the
 * single permission IS the role's whole purpose, so it's attached
 * immediately.
 *
 * The permission is created here (findOrCreate-style) rather than waiting
 * for `shield:generate` — the generator will simply reuse the existing
 * row later, and this way the migration is self-contained.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        // Permission — name matches what Shield generates for the
        // LeadExport page (pascal case + ":" separator, class subject).
        $permissionId = DB::table('permissions')
            ->where('name', 'View:LeadExport')
            ->where('guard_name', 'web')
            ->value('id');

        if (! $permissionId) {
            $permissionId = DB::table('permissions')->insertGetId([
                'name' => 'View:LeadExport',
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Role
        $roleId = DB::table('roles')
            ->where('name', 'marketing_agency')
            ->where('guard_name', 'web')
            ->value('id');

        if (! $roleId) {
            $roleId = DB::table('roles')->insertGetId([
                'name' => 'marketing_agency',
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Attach
        $exists = DB::table('role_has_permissions')
            ->where('permission_id', $permissionId)
            ->where('role_id', $roleId)
            ->exists();

        if (! $exists) {
            DB::table('role_has_permissions')->insert([
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ]);
        }

        // Non-admin roles get an EMPTY sidebar by default (see
        // NavigationResolver::ADMIN_ROLES) — without this row the agency
        // would log in to a blank panel and someone would have to enable
        // the item manually in "Бокова панель". One explicit override
        // makes the account work out of the box.
        DB::table('navigation_settings')->updateOrInsert(
            [
                'role' => 'marketing_agency',
                'item_key' => \App\Filament\Pages\Marketing\LeadExport::class,
            ],
            [
                'is_active' => true,
                'group_sort' => 0,
                'item_sort' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        // Spatie caches the whole permission table — without this the new
        // role/permission may not be visible until the cache expires.
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $roleId = DB::table('roles')
            ->where('name', 'marketing_agency')
            ->where('guard_name', 'web')
            ->value('id');

        if ($roleId) {
            DB::table('role_has_permissions')->where('role_id', $roleId)->delete();
            DB::table('model_has_roles')->where('role_id', $roleId)->delete();
            DB::table('roles')->where('id', $roleId)->delete();
        }

        DB::table('navigation_settings')
            ->where('role', 'marketing_agency')
            ->delete();

        // The permission itself is left in place — shield:generate owns it.

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
