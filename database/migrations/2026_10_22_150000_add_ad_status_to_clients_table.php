<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ad-audience qualification of a client (Customer Match lists in Google Ads).
 *
 * ad_status: null (not checked yet) | qualified (has at least one non-junk lead or an order;
 *   sticky — never reverts) | unqualified (only new/junk leads so far; re-checked on every run).
 * ad_synced_status: which list the client was last uploaded to ('qualified' / 'unqualified'),
 *   so the next run uploads only changes instead of the whole database.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (! Schema::hasColumn('clients', 'ad_status')) {
                $table->string('ad_status', 20)->nullable()->index();
                $table->timestamp('ad_status_checked_at')->nullable();
                $table->string('ad_synced_status', 20)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['ad_status', 'ad_status_checked_at', 'ad_synced_status']);
        });
    }
};
