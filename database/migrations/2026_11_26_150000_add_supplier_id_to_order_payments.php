<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * order_payments.supplier_id — явний зв'язок оплати з постачальником.
 *
 * Раніше постачальник був лише текстом у payer_name (його визначав синк зі
 * старого user_id_by_type). Для звіту «Оборот постачальників» потрібен
 * справжній зв'язок. Нові дані заповнює OrderPaymentsSyncMapper; тут —
 * одноразове заповнення вже наявних рядків за збігом назви.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_payments', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('payer_name')
                ->constrained('suppliers')->nullOnDelete();
            $table->index(['payer_type', 'supplier_id', 'paid_at'], 'order_payments_supplier_turnover_idx');
        });

        DB::statement("
            UPDATE order_payments op
            JOIN suppliers s ON TRIM(s.name) = TRIM(op.payer_name)
            SET op.supplier_id = s.id
            WHERE op.payer_type = 'supplier' AND op.supplier_id IS NULL
        ");
    }

    public function down(): void
    {
        Schema::table('order_payments', function (Blueprint $table) {
            $table->dropIndex('order_payments_supplier_turnover_idx');
            $table->dropConstrainedForeignId('supplier_id');
        });
    }
};
