<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Annulled-payments group (user decision, 2026-07-07): old-CRM payment
 * rows whose order cannot be resolved (the bank-import bot wrote the
 * invoice-series prefix "9126" into order_id, or the order itself was
 * skipped by sync) are no longer silently SKIPPED. They are imported
 * with order_id = NULL, status = 'canceled' and classification_status =
 * 'annulled' — visible in their own group on the "Платежі" page (audit
 * trail preserved, "void, don't delete"), excluded from every total
 * (analytics only counts status='received'), reviewable/re-linkable by
 * hand later once the re-linking workflow is designed.
 *
 * Hence order_id must become nullable; the FK is preserved for real
 * order references.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Plain MODIFY keeps the existing FK; MySQL allows NULLs in a
        // foreign key column — NULL simply means "no parent row".
        DB::statement('ALTER TABLE order_payments MODIFY order_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        DB::statement('DELETE FROM order_payments WHERE order_id IS NULL');
        DB::statement('ALTER TABLE order_payments MODIFY order_id BIGINT UNSIGNED NOT NULL');
    }
};
