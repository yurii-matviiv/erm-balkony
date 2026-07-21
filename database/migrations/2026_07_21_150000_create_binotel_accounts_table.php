<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Binotel API accounts — the gateway connection for the future telephony
 * integration. This table stores only credentials and connection metadata;
 * calls themselves belong to lead_calls / future Binotel-specific tables.
 *
 * Pattern mirrors PrivatBank and Mobile operators: credentials are managed
 * through an Integration resource, secret is encrypted via the model cast,
 * and the View page performs a live API check.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('binotel_accounts', function (Blueprint $table) {
            $table->id();

            $table->string('display_name');
            $table->string('company_name')->nullable();

            // Binotel API v4 credentials. company_id is added by the next
            // migration because this one may already have run locally.
            $table->string('api_key');
            $table->text('api_secret');

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('display_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('binotel_accounts');
    }
};
