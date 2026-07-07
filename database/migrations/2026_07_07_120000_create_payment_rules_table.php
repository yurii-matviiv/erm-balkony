<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-editable distribution rules for the NEW payment pipeline (see
 * CLAUDE.md "Платежі — принципи" and the "Платіжні правила" page).
 *
 * A rule says: WHEN a bank transaction / payment matches a condition
 * (field + match type + pattern), THEN classify it (category +
 * sub_category). Rules are ordered by priority (lower = first), can be
 * switched off without deleting, and live in the DB so the admin manages
 * them without a developer.
 *
 * NOTE: nothing consumes these rules yet — this is the settings
 * foundation for the upcoming bank-transactions import (per-transaction
 * reconciliation). The old google_ads_pay import shows the shape such a
 * rule will take: "OSND contains GOOGLE*ADS → marketing / google_ads".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_rules', function (Blueprint $table): void {
            $table->id();

            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('priority')->default(100);

            // What to look at: the bank payment purpose (OSND/comment),
            // the counterparty name, or the counterparty IBAN.
            $table->string('match_field', 30)->default('comment');
            $table->string('match_type', 20)->default('contains');
            $table->string('pattern');

            // What to set when matched.
            $table->string('set_category', 30)->nullable();
            $table->string('set_sub_category', 50)->nullable();

            $table->text('note')->nullable();

            $table->timestamps();

            $table->index(['is_active', 'priority']);
        });

        // Seed the one rule we already KNOW works — documented example
        // for the admin, mirroring the google_ads_pay import.
        DB::table('payment_rules')->insert([
            'name' => 'Google Ads — автосписання з картки',
            'is_active' => true,
            'priority' => 10,
            'match_field' => 'comment',
            'match_type' => 'contains',
            'pattern' => 'GOOGLE',
            'set_category' => 'marketing',
            'set_sub_category' => 'google_ads',
            'note' => 'Приклад-еталон: списання Google Ads видно в призначенні платежу як "GOOGLE*ADS...". Використовується майбутнім імпортом банківських транзакцій.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_rules');
    }
};
