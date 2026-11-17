<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Скільки вихідних дзвінків клієнту зробили (за Binotel): після 2 невдалих зʼявляється кнопка «Клієнт не відповідає». */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->unsignedTinyInteger('call_attempts')->default(0)->after('first_call_checked_at');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->dropColumn('call_attempts');
        });
    }
};
