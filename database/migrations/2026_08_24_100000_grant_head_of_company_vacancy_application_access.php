<?php

use App\Filament\Resources\VacancyApplicationResource;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Керівник компанії" is a broadly read-only role (see project notes: bачить
 * усе, нічого не редагує) — but "Додати заявку" on "Заявки на вакансії" is a
 * deliberate, explicit exception the user asked for, not a general write
 * grant. This migration only opens that one door: view access to the
 * VacancyApplication list (so the page — and its header button — is
 * reachable at all) plus Create access (so the button actually works).
 * Update/Delete stay untouched, i.e. still denied by default.
 *
 * Role name is the literal Ukrainian string, NOT a slug — UsersSyncMapper
 * creates this exact role (`Керівник компанії`, guard `web`) straight from
 * the legacy `atom_roles.description` value when a user with the old
 * ROLE_HEAD_OF_COMPANY (id=12) is synced. Using any other name here would
 * create a second, disconnected role that the synced user never gets.
 *
 * Same pattern as create_marketing_agency_role: permissions/role/pivot rows
 * inserted directly via DB::table (self-contained, doesn't wait for
 * `shield:generate`), plus a navigation_settings row so the item actually
 * shows up in this role's sidebar — mirrors exactly what toggling it on in
 * "Бокова панель" would do (see SidebarPermissionSync), just done here for
 * a role whose sidebar hasn't been configured yet at all.
 */
return new class extends Migration
{
    private const ROLE_NAME = 'Керівник компанії';

    /**
     * @var list<string>
     */
    private const PERMISSIONS = [
        'ViewAny:VacancyApplication',
        'View:VacancyApplication',
        'Create:VacancyApplication',
    ];

    public function up(): void
    {
        $now = now();

        $roleId = DB::table('roles')
            ->where('name', self::ROLE_NAME)
            ->where('guard_name', 'web')
            ->value('id');

        if (! $roleId) {
            $roleId = DB::table('roles')->insertGetId([
                'name' => self::ROLE_NAME,
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach (self::PERMISSIONS as $permissionName) {
            $permissionId = DB::table('permissions')
                ->where('name', $permissionName)
                ->where('guard_name', 'web')
                ->value('id');

            if (! $permissionId) {
                $permissionId = DB::table('permissions')->insertGetId([
                    'name' => $permissionName,
                    'guard_name' => 'web',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

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
        }

        // Make the "Заявки на вакансії" item actually visible in this
        // role's sidebar — without this the permissions above are granted
        // but the page (and its "Додати заявку" button) is still nowhere
        // to be clicked from, since non-admin roles default to an empty
        // sidebar (see NavigationResolver::ADMIN_ROLES).
        DB::table('navigation_settings')->updateOrInsert(
            [
                'role' => self::ROLE_NAME,
                'item_key' => VacancyApplicationResource::class,
            ],
            [
                'is_active' => true,
                'group_sort' => 0,
                'item_sort' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $roleId = DB::table('roles')
            ->where('name', self::ROLE_NAME)
            ->where('guard_name', 'web')
            ->value('id');

        if ($roleId) {
            foreach (self::PERMISSIONS as $permissionName) {
                $permissionId = DB::table('permissions')
                    ->where('name', $permissionName)
                    ->where('guard_name', 'web')
                    ->value('id');

                if ($permissionId) {
                    DB::table('role_has_permissions')
                        ->where('role_id', $roleId)
                        ->where('permission_id', $permissionId)
                        ->delete();
                }
            }
        }

        // Role itself is left in place — it belongs to UsersSyncMapper /
        // the user's broader role setup, not to this one grant. The
        // permission rows are left too, same reasoning as
        // create_marketing_agency_role (shield:generate owns them).

        DB::table('navigation_settings')
            ->where('role', self::ROLE_NAME)
            ->where('item_key', VacancyApplicationResource::class)
            ->delete();

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
