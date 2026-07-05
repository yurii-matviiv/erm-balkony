<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payments module, step 1 (see CLAUDE.md "Платежі — принципи" — the
 * single source of truth for this logic):
 *
 * - `created_by` (принцип 4): who entered the payment. For rows synced
 *   from the old CRM this is resolved from old `orders_payments.user_id`
 *   (DB column comment: "хто редагує") via users.legacy_id, so authorship
 *   survives the migration; for rows created in the new system it is set
 *   automatically at creation time.
 *
 * - `classification_status` (принцип 2): 'classified' = the row fits the
 *   new clean structure; 'unsorted' = carried over from the old DB
 *   without confidently mapping to the new categories — awaiting manual
 *   (or rule-based) sorting via the "Платежі" page's "Не розібрані"
 *   filter. New-system rows are born 'classified' (принцип 3).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['order_payments', 'expenses'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->foreignId('created_by')
                    ->nullable()
                    ->after('comment')
                    ->constrained('users')
                    ->nullOnDelete();

                $table->string('classification_status', 20)
                    ->default('classified')
                    ->after('status');

                $table->index('classification_status', $tableName.'_classification_idx');
            });
        }
    }

    public function down(): void
    {
        foreach (['order_payments', 'expenses'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->dropConstrainedForeignId('created_by');
                $table->dropIndex($tableName.'_classification_idx');
                $table->dropColumn('classification_status');
            });
        }
    }
};
