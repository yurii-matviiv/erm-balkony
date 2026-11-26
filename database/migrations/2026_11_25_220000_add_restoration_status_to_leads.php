<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Розділення «Програми Відновлення» на два поля (так само, як у старій CRM):
 *  - is_restoration_program: NULL = не питали, 0 = ні, 1 = так;
 *  - restoration_status: статус заявки по програмі 1–7, лише коли програма = 1.
 * Числа збігаються зі старою CRM і з Lead::restorationStatusOptions() — змінювати разом.
 */
return new class extends Migration
{
    private const PROGRAM_COMMENT = 'Програма Відновлення: NULL=не питали, 0=не по програмі, 1=так, по програмі (статус — у restoration_status)';

    private const STATUS_COMMENT = 'Статус по програмі (лише коли is_restoration_program=1): 1=нова, 2=думає, 3=чекає комісію/гроші, 4=в роботі (гроші є), 5=ігнорує, 6=відмова: дорого, 7=відмова: не підходить програма';

    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->unsignedTinyInteger('restoration_status')->nullable()->after('is_restoration_program')->comment(self::STATUS_COMMENT);
        });

        // Раніше статус 1–7 лежав у самому is_restoration_program — переносимо його в нову колонку.
        // Порядок присвоєнь важливий: спочатку статус бере старе значення, потім програма стає 1.
        DB::table('leads')->where('is_restoration_program', '>=', 1)->update([
            'restoration_status' => DB::raw('is_restoration_program'),
            'is_restoration_program' => 1,
        ]);

        Schema::table('leads', function (Blueprint $table): void {
            $table->boolean('is_restoration_program')->nullable()->comment(self::PROGRAM_COMMENT)->change();
        });
    }

    public function down(): void
    {
        // Повертаємо статус у старе поле (1–7), програму «так» без статусу лишаємо як 1.
        DB::table('leads')->whereNotNull('restoration_status')->update([
            'is_restoration_program' => DB::raw('restoration_status'),
        ]);

        Schema::table('leads', function (Blueprint $table): void {
            $table->dropColumn('restoration_status');
        });
    }
};
