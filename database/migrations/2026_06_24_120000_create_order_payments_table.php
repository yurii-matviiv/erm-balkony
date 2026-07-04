<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historical payment records from the old CRM's `orders_payments` table.
 *
 * Schema overview based on direct reading of Order.php / Payment.php from
 * the old codebase (dev.ERM-btv — read-only reference):
 *
 * Each row is one "money movement" tied to an order:
 *  - direction: 'income' (money coming IN from client) or
 *               'outgo'  (money going OUT to supplier/installer/gauger)
 *  - payer_type: who the money is from/to:
 *      'client'   — client paid us
 *      'supplier' — we paid a supplier
 *      'installer'— salary to an installer
 *      'gauger'   — payment to a measurer
 *      'expense'/'office' — internal office costs (rarely tied to a single order)
 *  - payment_method: how money moved
 *      'cash' / 'cashless' / 'card' / 'courier' / 'installer'
 *  - status: 'received' (confirmed), 'sent' (dispatched but not yet confirmed),
 *            'pending' (planned/expected)
 *  - category: 'salary' rows are salary calculations (the old system filtered
 *    these out of the order payment block) — kept here for completeness/
 *    filtering, not shown in the main payment table by default.
 *
 * payer_name is resolved AT SYNC TIME from the raw payer_legacy_id:
 *  - client   → clients.first_name  (which now holds the full name)
 *  - supplier → suppliers.name      (via suppliers.legacy_id)
 *  - installer/gauger → users.name  (via users.legacy_id)
 * Stored as a plain string so the display never needs extra JOINs just to
 * show who paid/was paid — historical names stay readable even if a user/
 * supplier record is later deleted or renamed.
 *
 * Intentionally NOT brought over:
 * - bank_id / order_kind (Privatbank-specific fields for the online payment
 *   link flow — the old integration is gone and we're building a new one)
 * - fop_account (which of our own legal entities was used) — kept as the
 *   raw integer from the old system for now; when we build our own "юридичні
 *   особи" module it'll get a proper FK. Stored as fop_account_legacy_id.
 * - user_id (who LOGGED the payment entry) — not important for display,
 *   could reconstruct from legacy_id if ever needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_payments', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('legacy_id')->nullable()->unique()
                ->comment('old orders_payments.id — used for idempotent re-sync');

            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();

            // income = клієнт нам платить; outgo = ми платимо комусь
            $table->enum('direction', ['income', 'outgo']);

            // client / supplier / installer / gauger / expense / office
            $table->string('payer_type', 30);

            // Human-readable name resolved at sync time (see class docblock)
            $table->string('payer_name')->nullable();

            // cash / cashless / card / courier / installer
            $table->string('payment_method', 30)->nullable();

            $table->decimal('amount', 12, 2)->default(0);

            // received = підтверджено; sent = надіслано; pending = заплановано
            $table->string('status', 20)->default('received');

            // 'salary' means this row is a salary payment (filtered separately)
            // other known values: 'order', 'office', 'tax', 'marketing', null
            $table->string('category', 30)->nullable();

            $table->text('comment')->nullable();

            // When the payment was made / when it was confirmed received
            $table->date('paid_at')->nullable();
            $table->date('received_at')->nullable();

            // Privatbank transaction reference — for future integration
            $table->string('privatbank_num', 50)->nullable();

            // Raw ID of the FOP/legal entity from old system (orders.fop_account)
            // Will get a proper FK when we build the "наші юридичні особи" module
            $table->string('fop_account_legacy_id', 30)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_payments');
    }
};
