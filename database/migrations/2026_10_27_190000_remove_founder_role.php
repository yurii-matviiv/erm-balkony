<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Removes the unused `founder` role (0 users, 0 permissions). Its business
 * meaning is covered by the real "Керівник компанії" role. Runs on every
 * environment (local + hosting) via `php artisan migrate`; idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        $ids = DB::table('roles')->where('name', 'founder')->pluck('id');

        if ($ids->isNotEmpty()) {
            DB::table('model_has_roles')->whereIn('role_id', $ids)->delete();
            DB::table('role_has_permissions')->whereIn('role_id', $ids)->delete();
            DB::table('roles')->whereIn('id', $ids)->delete();
        }

        DB::table('navigation_settings')->where('role', 'founder')->delete();
    }

    public function down(): void
    {
        // Intentionally empty: the role is gone for good.
    }
};
