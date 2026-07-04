<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One application ("заявка") from a candidate for a specific vacancy.
 * A candidate can have several of these over time (e.g. applies again
 * months later for a different position) — that's why this is its own
 * table rather than flat columns on Candidate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vacancy_applications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->foreignId('vacancy_id')->nullable()->constrained('vacancies')->nullOnDelete();

            // Where the candidate came from. Free string (not an enum like
            // the old `candidates.advertising_channel`) so new channels can
            // show up without a migration; the options offered in the admin
            // form are still the same ones the old system tracked.
            $table->string('advertising_channel')->nullable();

            // "Цільова заявка" — whether this was a deliberate application
            // for this specific role, as opposed to a generic/unsolicited
            // contact that got slotted into a vacancy after the fact.
            $table->boolean('is_targeted')->default(false);

            $table->text('comment')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vacancy_applications');
    }
};
