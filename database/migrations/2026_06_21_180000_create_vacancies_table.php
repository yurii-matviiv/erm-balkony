<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Open positions ("вакансії") to which a candidate can apply.
 *
 * The old system never had this as a real, editable table — it only had a
 * hardcoded 3-value enum on `candidates.job_vacancy` (installer / assistant
 * / manager), so there was no admin UI to add/rename/close a vacancy. This
 * table replaces that enum with real rows, seeded below with the same 3
 * positions so we start from what already existed, but going forward new
 * vacancies can be added freely from the admin panel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vacancies', function (Blueprint $table) {
            $table->id();
            $table->string('name');

            // Whether this position is currently open for applications.
            // Closing a vacancy should not be done by deleting it — past
            // applications still need to point somewhere meaningful.
            $table->boolean('is_active')->default(true);

            $table->text('comment')->nullable();

            $table->timestamps();
        });

        // Seed with the 3 positions that existed (as enum values) in the
        // old system, so the vacancy list isn't empty on day one.
        DB::table('vacancies')->insert([
            ['name' => 'Монтажник', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Помічник', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Менеджер', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('vacancies');
    }
};
