<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * cash / cashless on settlement operations — same axis and same keys as
 * payment_method everywhere else in the system (order_payments/expenses),
 * so future cross-module reports don't need value mapping. Label for
 * 'cashless' here is "На рахунок" (user's wording for this module).
 *
 * Nullable: rows created before this column existed have no method.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settlements', function (Blueprint $table) {
            $table->string('payment_method', 20)->nullable()->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('settlements', function (Blueprint $table) {
            $table->dropColumn('payment_method');
        });
    }
};
