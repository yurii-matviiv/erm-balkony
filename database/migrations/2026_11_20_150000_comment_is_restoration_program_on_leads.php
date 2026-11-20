<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Пояснення значень колонки прямо в базі (видно в phpMyAdmin). Числа збігаються зі старою CRM
 * і з Lead::restorationProgramOptions() — змінювати разом.
 */
return new class extends Migration
{
    private const COMMENT = 'Програма Відновлення: NULL=не питали, 0=не по програмі, 1=нова, 2=думає, 3=чекає комісію/гроші, 4=в роботі (гроші є), 5=ігнорує, 6=відмова: дорого, 7=відмова: не підходить програма';

    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->boolean('is_restoration_program')->nullable()->comment(self::COMMENT)->change();
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->boolean('is_restoration_program')->nullable()->comment('')->change();
        });
    }
};
