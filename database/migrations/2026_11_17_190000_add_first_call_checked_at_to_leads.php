<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Коли востаннє питали Binotel про перший вихідний дзвінок по заявці (щоб не стукати в API при кожному відкритті). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->dateTime('first_call_checked_at')->nullable()->after('stage');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->dropColumn('first_call_checked_at');
        });
    }
};
