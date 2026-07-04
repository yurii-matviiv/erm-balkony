<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Missed in the original create_candidates_table migration — every other
 * synced table (users, clients, suppliers) has this. Without it,
 * CandidatesSyncMapper/AddCandidateSyncMapper can't do idempotent
 * upserts. See AbstractSyncMapper::resolveLegacyId() for why
 * AddCandidateSyncMapper's values are offset by 1,000,000 (it shares this
 * same column/table with CandidatesSyncMapper).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->unsignedBigInteger('legacy_id')->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->dropColumn('legacy_id');
        });
    }
};
