<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Direct FK to the FOP account directory (privatbank_accounts) — makes
 * the "Рахунок" filter on the Платежі page real instead of the raw
 * legacy string in fop_account_legacy_id (kept for traceability).
 *
 * Resolution order at sync time (user decision, 2026-07-07):
 *   1. old fop_account ∈ {35,47,60} (old user ids) → that user's FOP;
 *   2. otherwise → the FOP of the ORDER'S MANAGER;
 *   3. manager has no own FOP → NULL ("не визначено").
 * Cash vs cashless stays a separate axis (payment_method) — the FOP here
 * answers "whose books is this on", not "how it was paid".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_payments', function (Blueprint $table): void {
            $table->foreignId('fop_account_id')
                ->nullable()
                ->after('fop_account_legacy_id')
                ->constrained('privatbank_accounts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_payments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('fop_account_id');
        });
    }
};
