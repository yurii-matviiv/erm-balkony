<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A person who has applied (or might apply again) for a job. Kept
 * deliberately separate from Client and Supplier — same reasoning as the
 * Client/Supplier split earlier in this project: a candidate is its own
 * kind of contact, not "a type of" anything else.
 *
 * Phone is NOT unique on purpose: when a known candidate re-applies, the
 * person creating the application is expected to search by phone and pick
 * the existing record (see VacancyApplicationResource's candidate select,
 * which has a "create new" option for when it's genuinely a different
 * person). A hard unique constraint would make that human judgement call
 * impossible to override.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidates', function (Blueprint $table) {
            $table->id();

            $table->string('phone', 30);
            $table->string('last_name')->nullable();
            $table->string('first_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->string('email')->nullable();

            $table->timestamps();

            $table->index('phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidates');
    }
};
