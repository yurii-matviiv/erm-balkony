<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the fields required by the "old DB -> new DB" sync tool.
 *
 * - legacy_id: the primary key of the matching row in the OLD database
 *   (table `users`). It is the only thing that lets us know whether a
 *   given new user was already imported, and lets us re-run the sync
 *   safely (upsert by legacy_id) without creating duplicates.
 * - last_name / middle_name: the old system stores surname ("прізвище")
 *   and patronymic ("по батькові") separately from the first name, but
 *   the data quality there is poor. We copy the raw values as-is for now;
 *   any cleanup/splitting logic will run later, directly on this table.
 * - phone: contact phone number copied as-is from the old system.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Reference to the row in the OLD database this user came from.
            // Nullable: users created directly in the new system have no legacy_id.
            $table->unsignedBigInteger('legacy_id')->nullable()->unique()->after('id');

            $table->string('last_name')->nullable()->after('name');
            $table->string('middle_name')->nullable()->after('last_name');
            $table->string('phone', 20)->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['legacy_id', 'last_name', 'middle_name', 'phone']);
        });
    }
};
