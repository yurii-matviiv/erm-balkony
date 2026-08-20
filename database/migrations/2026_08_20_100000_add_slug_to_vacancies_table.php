<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Public URL slug for a vacancy's Instagram-ad landing form
 * (`/vacancy/{slug}`). Nullable — a vacancy only gets one once someone
 * actually points an ad campaign at it; most vacancies stay without a
 * public form.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vacancies', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('name');
        });

        // First real campaign: "Монтажник" gets the slug used by the
        // Instagram ad going live now.
        DB::table('vacancies')->where('name', 'Монтажник')->update(['slug' => 'montazhnyk']);
    }

    public function down(): void
    {
        Schema::table('vacancies', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
