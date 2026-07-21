<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who physically handed the money over during a collection (інкасація) —
 * a manager or the head of sales. Separate from created_by (who typed
 * the row into the system): the person entering the operation is not
 * necessarily the person who gave the cash.
 *
 * Nullable: transfers don't have this, and collection rows created
 * before this column existed have no value.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settlements', function (Blueprint $table) {
            $table->foreignId('handed_by')
                ->nullable()
                ->after('recipient_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('settlements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('handed_by');
        });
    }
};
