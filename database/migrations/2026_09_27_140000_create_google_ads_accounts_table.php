<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Google Ads API gateway connection — same pattern as PrivatBank, Mobile
 * operators and Binotel: credentials managed through an Integration
 * resource, secret encrypted via the model cast, View page runs a live
 * API check. The whole service-account JSON key is stored encrypted here
 * (not as a file path) so a hosting deploy needs no manual file transfer —
 * the admin re-pastes the key once through this page after the schema
 * migrates.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_ads_accounts', function (Blueprint $table) {
            $table->id();

            $table->string('display_name');
            // Google Ads customer ID, digits only (no dashes), e.g. 7723114436.
            $table->string('customer_id');
            // Full JSON content of the service account key file.
            $table->text('service_account_json');

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('display_name');
            $table->unique('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_ads_accounts');
    }
};
