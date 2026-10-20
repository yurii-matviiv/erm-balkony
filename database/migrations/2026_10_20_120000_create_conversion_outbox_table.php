<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Outbox for offline conversions sent to Google Ads (Data Manager API).
 *
 * One row = one conversion event for one lead: kind `qualified` (lead left
 * the "new" status and is not junk) or `sale` (an order exists for the lead).
 * Rows wait in `pending` until `send_after` (buffer against accidental status
 * changes), are re-validated right before sending, and only then go to Google.
 * Writes ONLY to the new database — the legacy connection is never touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversion_outbox', function (Blueprint $table) {
            $table->id();

            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();

            // qualified | sale
            $table->string('kind', 20);

            // Stable unique id sent to Google as transactionId (dedup / restate).
            $table->string('transaction_id', 100)->unique();

            // gclid (gbraid/wbraid rows are recorded as skipped — see ConversionPlanner)
            $table->string('click_id_type', 10)->nullable();
            $table->string('click_id', 255)->nullable();
            $table->string('conversion_action_id', 30)->nullable();

            $table->decimal('value', 12, 2)->default(0);
            $table->string('currency', 3)->default('UAH');

            // UTC moment the status change / order was first observed by sync.
            $table->timestamp('event_time')->nullable();

            // Lead state (legacy_status or stage/status) the row was planned for;
            // a change of it restarts the buffer.
            $table->string('lead_state', 100)->nullable();

            // pending | validated | sent | confirmed | failed | cancelled | skipped
            $table->string('status', 20)->default('pending')->index();
            $table->string('skip_reason', 60)->nullable();

            $table->timestamp('send_after')->nullable()->index();
            $table->unsignedSmallInteger('attempts')->default(0);

            $table->timestamp('validated_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->string('google_request_id', 100)->nullable()->index();
            $table->text('last_error')->nullable();

            $table->timestamps();

            $table->unique(['lead_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversion_outbox');
    }
};
