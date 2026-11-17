<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Модуль «Зарплата» (менеджерська з/п з замовлення) — два поля зі старої CRM:
 *  - formula_salary: 'percentage' (фіксований відсоток) або 'multiplier'
 *    (відсоток залежить від «якості продажу» — див. SalaryService::multiplierPercent);
 *  - percentage_value: відсоток для формули 'percentage' (порожньо = 7%, як у старій).
 * Заповнюються синхронізацією зі старої БД (OrdersSyncMapper) і редагуються
 * на сторінці «Модуль З/П».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('formula_salary', 20)->default('percentage')->after('bonus');
            $table->decimal('percentage_value', 4, 1)->nullable()->after('formula_salary');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['formula_salary', 'percentage_value']);
        });
    }
};
