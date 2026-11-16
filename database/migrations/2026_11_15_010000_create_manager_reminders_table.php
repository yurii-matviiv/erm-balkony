<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Reminders ("нагадування") a manager sets for himself, optionally tied to a lead —
// the old CRM's manager_reminders table (lead card + /manager/reminders page).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manager_reminders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->cascadeOnDelete();
            $table->text('reminder_text');
            $table->dateTime('reminder_datetime');
            // Eisenhower matrix as in the old CRM: important/urgent combinations.
            $table->string('reminder_type')->default('important_urgent');
            $table->boolean('is_completed')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'is_completed', 'reminder_datetime']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manager_reminders');
    }
};
