<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Поля картки монтажника зі старої CRM (форма /installer_edit/{id}), яких ще не було в users.
 * Заповнюються синхронізацією (UsersSyncMapper) і редагуються в картці монтажника.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('additional_phone', 20)->nullable()->after('phone');
            $table->boolean('viber_is')->default(false)->after('additional_phone');
            $table->string('district_kyiv', 40)->nullable()->after('viber_is');
            $table->text('registration_address')->nullable()->after('district_kyiv');
            $table->text('residence_address')->nullable()->after('registration_address');
            $table->unsignedSmallInteger('user_percent')->nullable()->after('residence_address');
            $table->text('comment')->nullable()->after('user_percent');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['additional_phone', 'viber_is', 'district_kyiv', 'registration_address', 'residence_address', 'user_percent', 'comment']);
        });
    }
};
