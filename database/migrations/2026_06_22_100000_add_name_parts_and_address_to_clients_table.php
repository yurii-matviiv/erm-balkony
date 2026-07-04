<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds structured name/address fields used by the new Lead intake form
 * (see LeadResource). Old, already-synced clients keep their single
 * `name` and free-text `address` fields untouched — these new columns are
 * simply nullable and start empty for them. Client::fullName() prefers the
 * structured fields when present and falls back to the legacy `name`.
 *
 * Why split: the lead form collects last/first/middle name and a
 * street/house/apartment/floor address separately, per explicit request —
 * a single flat string can't be edited/validated as cleanly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('last_name')->nullable()->after('name');
            $table->string('first_name')->nullable()->after('last_name');
            $table->string('middle_name')->nullable()->after('first_name');

            $table->string('street')->nullable()->after('address');
            $table->string('house_number', 20)->nullable()->after('street');
            $table->string('apartment_number', 20)->nullable()->after('house_number');
            $table->string('floor', 20)->nullable()->after('apartment_number');
            $table->string('city')->default('Київ')->after('floor');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'last_name', 'first_name', 'middle_name',
                'street', 'house_number', 'apartment_number', 'floor', 'city',
            ]);
        });
    }
};
