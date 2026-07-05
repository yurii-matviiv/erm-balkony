<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `is_active` — mirrored from the old CRM's users.is_active (1/0), which
 * was never synced before: the new system had no notion of a deactivated
 * employee, so UI selects (e.g. "Хто вніс" on the Платежі page) listed
 * everyone who ever worked here. Synced by UsersSyncMapper; defaults to
 * true so accounts created directly in the new system are active.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true)->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('is_active');
        });
    }
};
