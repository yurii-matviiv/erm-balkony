<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Forward-looking schema for the planned Binotel call-tracking
 * integration — NOT populated yet, no UI yet, no sync mapper (the old
 * system has zero structured call-log data: it only ever recorded
 * 'get_call_binotel'/'binotel_chat' as a `leads.source` value, with no
 * call duration/recording/call id anywhere — confirmed, no dedicated
 * table exists in the old DB).
 *
 * One lead can have MANY calls over its lifetime (follow-ups), which is
 * why this is its own 1:many table rather than living inside
 * LeadMarketingData (a 1:1 snapshot captured once at lead creation — a
 * fundamentally different shape of data).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_calls', function (Blueprint $table) {
            $table->id();

            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();

            // Binotel's own identifier for this call, for de-duplication
            // when the webhook/API integration is eventually built.
            $table->string('external_call_id')->nullable()->unique();

            $table->string('direction')->nullable(); // inbound / outbound
            $table->string('phone')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('recording_url')->nullable();
            $table->string('status')->nullable(); // answered / missed / busy ...
            $table->dateTime('called_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_calls');
    }
};
