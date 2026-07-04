<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `order_files` — stores file links attached to orders (and later to invoices).
 *
 * In the OLD system, each file type lived in its own dedicated table:
 *   specification_file       → "Специфікація до договору"
 *   invoice_from_supplier    → "Рахунок від постачальника"
 *   paid_invoice_to_supplier → "Оплачений рахунок постачальнику"
 *   commercial_from_supplier → "Комерційна пропозиція від постачальника"
 *
 * All four had the same shape: (id, order_id, url, file_name).
 * Files were stored on Google Drive — only the public URL was saved in DB.
 *
 * In the NEW system we consolidate all four into one table, tagged by
 * `type`, so adding new file types in the future doesn't require new tables.
 *
 * `legacy_source_table` + `legacy_id` together uniquely identify a row in
 * the old system — this composite key drives the idempotent sync (a re-run
 * won't duplicate rows). The UNIQUE constraint uses a generated column
 * approach: the (legacy_source_table, legacy_id) pair is the natural PK of
 * the old records.
 *
 * `invoice_id` is nullable — for now all imported files are linked only to
 * `order_id`. Once the `invoices` module is built, files can be re-linked
 * to a specific invoice without any schema change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_files', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();

            // Will be set once the invoices module is built.
            $table->unsignedBigInteger('invoice_id')->nullable()->index();

            $table->enum('type', [
                'specification',        // Специфікація до договору
                'supplier_invoice',     // Рахунок від постачальника
                'paid_invoice',         // Оплачений рахунок постачальнику
                'commercial',           // Комерційна пропозиція від постачальника
                'other',
            ])->default('other');

            $table->string('file_name');

            // Google Drive URL (legacy) or future storage URL.
            $table->text('url');

            // Which old table this row came from — used together with
            // `legacy_id` for idempotent sync (no duplicate imports).
            $table->string('legacy_source_table')->nullable();
            $table->unsignedBigInteger('legacy_id')->nullable();

            $table->timestamps();

            // Composite unique constraint for sync deduplication.
            $table->unique(['legacy_source_table', 'legacy_id'], 'order_files_legacy_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_files');
    }
};
