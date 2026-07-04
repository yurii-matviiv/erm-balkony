<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The address of the OBJECT/SITE where work happens for this specific
 * lead — deliberately separate from Client's own address (street/
 * house_number/apartment_number/floor/city on `clients`). Per explicit
 * realization during a conversation about the Lead edit page: a client
 * is one fixed person, but a given lead/request might be about a
 * DIFFERENT property than where the client themselves lives (a rental,
 * a relative's place, an office, ...) — so the two addresses can differ
 * and must not be conflated into one field.
 *
 * Prefixed `site_` (not `address_`) specifically to keep it visually and
 * conceptually distinct from Client's columns when both show up in code/
 * queries side by side.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('site_city')->nullable()->after('comment');
            $table->string('site_street')->nullable()->after('site_city');
            $table->string('site_house_number', 20)->nullable()->after('site_street');
            $table->string('site_apartment_number', 20)->nullable()->after('site_house_number');
            $table->string('site_floor', 20)->nullable()->after('site_apartment_number');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['site_city', 'site_street', 'site_house_number', 'site_apartment_number', 'site_floor']);
        });
    }
};
