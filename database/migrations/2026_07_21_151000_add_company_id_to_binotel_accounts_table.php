<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Binotel provides three integration identifiers: companyID, key, secret.
 * companyID is also present in API PUSH call payloads, so we store it on the
 * gateway account now to avoid guessing the source company later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('binotel_accounts', function (Blueprint $table) {
            $table->string('company_id')->after('company_name');
        });
    }

    public function down(): void
    {
        Schema::table('binotel_accounts', function (Blueprint $table) {
            $table->dropColumn('company_id');
        });
    }
};
