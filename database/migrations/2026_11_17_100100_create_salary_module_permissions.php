<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Права модуля «Зарплата» (у старій CRM це були «спеціальні» user_id та ролі
 * адмін/фінансист/керівник відділу продажу, тут — звичайні права ролей):
 *  - ViewAll:SalaryModule — бачить з/п ВСІХ менеджерів (фільтр по менеджеру);
 *    без нього користувач бачить лише свої замовлення;
 *  - Pay:SalaryModule — може змінювати формулу/відсоток і виплачувати з/п.
 * Видимість самої сторінки (View:SalaryModule) налаштовується в «Бокова панель».
 * Керівник компанії — лише перегляд (принцип його ролі).
 */
return new class extends Migration
{
    private function grants(): array
    {
        return [
            'ViewAll:SalaryModule' => ['super_admin', 'Адмін', 'Керівник відділу продажу', 'Керівник компанії'],
            'Pay:SalaryModule' => ['super_admin', 'Адмін', 'Керівник відділу продажу'],
        ];
    }

    public function up(): void
    {
        $now = now();

        foreach ($this->grants() as $permission => $roles) {
            $permissionId = DB::table('permissions')->where('name', $permission)->where('guard_name', 'web')->value('id')
                ?? DB::table('permissions')->insertGetId(['name' => $permission, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now]);

            foreach ($roles as $role) {
                $roleId = DB::table('roles')->where('name', $role)->where('guard_name', 'web')->value('id');

                if ($roleId) {
                    DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $roleId]);
                }
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::table('permissions')->whereIn('name', array_keys($this->grants()))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
