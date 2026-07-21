<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Взаєморозрахунки" — mutual-settlement operations between the company
 * and its two owners (participants).
 *
 * Money model (see CLAUDE.md "Модуль Взаєморозрахунки"):
 *   collection ("інкасація") — money is taken OUT of the company into the
 *     shared balance. The shared balance is implicit — it is not a table,
 *     it is SUM(collections) - SUM(transfers).
 *   transfer ("переказ") — money leaves the shared balance and lands on a
 *     specific participant's personal account (recipient_id).
 *
 * The company itself is NOT a user row — it is represented by the
 * 'collection' type (direction is unambiguous). Participants are real
 * users referenced by id, configured in app_settings
 * ('settlement_participant_ids') — no hardcoded ids anywhere (принцип 1
 * платежів застосовано і тут).
 *
 * Audit: created_by + created_at give "хто і коли (до хвилини) вніс
 * операцію"; paid_at is the business date of the operation itself
 * (may be backdated when entering an older operation).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlements', function (Blueprint $table) {
            $table->id();

            // collection = інкасація (company → shared balance)
            // transfer   = переказ   (shared balance → participant)
            $table->string('type', 20);

            // Participant receiving a transfer; NULL for collections
            $table->foreignId('recipient_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->decimal('amount', 12, 2);

            // Business date of the operation (editable, may be backdated)
            $table->date('paid_at');

            $table->text('comment')->nullable();

            // Who entered the row — created_at holds the exact time
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            // Common query patterns: date bar + type/recipient filters
            $table->index(['type', 'paid_at']);
            $table->index(['recipient_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlements');
    }
};
