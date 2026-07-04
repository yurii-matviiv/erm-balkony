<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Removes the legacy free-text `name` column from `clients` entirely —
 * per explicit request: the old system had one field `name`; the new
 * system's REAL, visible field for that same data is `first_name`, so the
 * value should live THERE, not in a separate "hidden" column that
 * Client::getFullNameAttribute() silently falls back to. Splitting that
 * value into last_name/first_name/middle_name properly is a SEPARATE,
 * later task (deliberately not done here) — this migration only moves
 * the whole old string into `first_name` as-is, unsplit, so it's at least
 * sitting in a real, expected field instead of `name`.
 *
 * Order matters: backfill BEFORE dropping the column, and only into rows
 * where `first_name` is still empty — a client created fresh in the new
 * system (via the Lead form, which always fills first_name) must never
 * be overwritten by old data.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('clients')
            ->whereNotNull('name')
            ->where('name', '!=', '')
            ->where(function ($query) {
                $query->whereNull('first_name')->orWhere('first_name', '');
            })
            ->update(['first_name' => DB::raw('name')]);

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('name')->nullable()->after('legacy_id');
        });

        // Lossy on purpose: there's no reliable way to tell, after the
        // fact, which first_name values originally came from the old
        // `name` column vs. were typed in fresh post-migration — down()
        // only exists so `migrate:rollback` doesn't error, not to
        // perfectly restore the old data shape.
    }
};
