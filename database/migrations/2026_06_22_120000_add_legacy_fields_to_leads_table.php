<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Missed in the original create_leads_table migration (same mistake as
 * candidates — see add_legacy_id_to_candidates_table). Needed for
 * LeadsSyncMapper to do idempotent upserts of the 14k old leads.
 *
 * `legacy_status` keeps the OLD system's raw status string (new,
 * processing, zamir, accepted, canceled, not_targeted, another_city,
 * for_later, reklamatsiya_amtech, propushcheno, vizyt_ofis, ...) verbatim,
 * because the new `stage`/`status`/`lost_reason` mapping (see
 * LeadsSyncMapper) is a deliberate, lossy simplification of a messier old
 * enum — keeping the raw value means nothing is silently lost if the
 * mapping needs revisiting later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->unsignedBigInteger('legacy_id')->nullable()->unique()->after('id');
            $table->string('legacy_status')->nullable()->after('lost_reason');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['legacy_id', 'legacy_status']);
        });
    }
};
