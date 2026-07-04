<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Заявка на замір" — the first concrete next step after a lead is
 * created, and the first place a crew (surveyor + installer) gets
 * attached to a lead. Created via the "Створити заявку на замір" action
 * on the Lead edit page (see EditLead), which also moves the lead's
 * `stage` to 'measurement_scheduled'.
 *
 * One lead can have several of these over time (re-scheduling creates a
 * new row rather than editing the old one — see Lead::latestMeasurement())
 * so the history of past appointments isn't lost.
 *
 * IMPORTANT business rule (per explicit request, repeated here because
 * it's easy to forget while building the next module): a crew always has
 * exactly one responsible person, and the system should always treat the
 * SURVEYOR (замірник) as that person — even when an installer (монтажник)
 * is also assigned, and even when they're literally the same person.
 * Anything that needs "who is responsible for this job" later (Order
 * module, payroll, etc.) should default to `surveyor_id`, not
 * `installer_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_measurements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();

            $table->date('scheduled_date');
            $table->time('scheduled_time')->nullable();

            // The responsible person for this job — see class docblock.
            $table->foreignId('surveyor_id')->constrained('users')->restrictOnDelete();

            // Often the same person as the surveyor; nullable because an
            // installer isn't always known/decided at the time the
            // measurement is booked.
            $table->foreignId('installer_id')->nullable()->constrained('users')->nullOnDelete();

            $table->text('comment')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_measurements');
    }
};
