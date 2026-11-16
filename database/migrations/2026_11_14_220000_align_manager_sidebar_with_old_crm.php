<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Manager sidebar = the OLD CRM menu, 1:1 (flat, no group headings):
 * Замовлення, Графік монтажів, Лід - заявка, Додати заявку, Не прийняті,
 * Оплати, Додати витрату, Модуль З/П. The last four are empty stub pages for
 * now (App\Filament\Manager\Pages\*), so the menu can be compared with the old
 * system before the pages themselves are rebuilt.
 *
 * Seeds the per-role overrides (navigation_settings) and the matching view
 * permissions (what SidebarPermissionSync would grant when toggling an item
 * on) for the role «Менеджер». Idempotent.
 */
return new class extends Migration
{
    private const ROLE = 'Менеджер';

    /** item_key => [item_sort, is_active] — empty group_label = flat menu. */
    private function items(): array
    {
        return [
            'App\\Filament\\Resources\\OrderResource' => [0, 1],
            'App\\Filament\\Pages\\MontageCalendar' => [1, 1],
            'App\\Filament\\Resources\\LeadResource' => [2, 1],
            'quick-create-lead' => [3, 1],
            'App\\Filament\\Manager\\Pages\\UnacceptedCalls' => [4, 1],
            'App\\Filament\\Manager\\Pages\\ManagerPayments' => [5, 1],
            'App\\Filament\\Manager\\Pages\\AddExpense' => [6, 1],
            'App\\Filament\\Manager\\Pages\\SalaryModule' => [7, 1],
            // Not in the old menu.
            'App\\Filament\\Pages\\Dashboard' => [20, 0],
        ];
    }

    public function up(): void
    {
        $now = now();

        foreach ($this->items() as $key => [$sort, $active]) {
            DB::table('navigation_settings')->updateOrInsert(
                ['role' => self::ROLE, 'item_key' => $key],
                ['group_label' => '', 'group_sort' => 0, 'item_sort' => $sort, 'is_active' => $active, 'updated_at' => $now, 'created_at' => $now],
            );
        }

        $roleId = DB::table('roles')->where('name', self::ROLE)->where('guard_name', 'web')->value('id');

        foreach (['UnacceptedCalls', 'ManagerPayments', 'AddExpense', 'SalaryModule'] as $page) {
            $name = 'View:'.$page;
            $permissionId = DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->value('id')
                ?? DB::table('permissions')->insertGetId(['name' => $name, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now]);

            if ($roleId) {
                DB::table('role_has_permissions')->updateOrInsert(['permission_id' => $permissionId, 'role_id' => $roleId], []);
            }
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Intentionally empty: menu overrides are plain settings, not schema.
    }
};
