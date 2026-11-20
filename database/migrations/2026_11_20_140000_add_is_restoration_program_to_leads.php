<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Заявка/замовлення йде по державній програмі «Відновлення».
 * NULL = менеджер ще не запитав клієнта, 1 = так, 0 = ні (той самий сенс, що в старій CRM: leads.is_restoration_program).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->boolean('is_restoration_program')->nullable()->after('profile');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->dropColumn('is_restoration_program');
        });
    }
};
