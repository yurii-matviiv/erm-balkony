<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Тип звернення" — what the client is actually contacting about. A lead
 * can have several at once (a client asking about both windows and a
 * balcony in one call) — see lead_lead_service_type pivot.
 *
 * Seeded from the OLD system's `leads.order_type` enum (real usage counts
 * checked first): window, balcony, window_in_cottage, window_repair,
 * glass_unit_replacement, balcony_cladding, balcony_with_takeout,
 * mosquito_net, turnkey_balcony, windowsill, internal_roller_blinds.
 * Deliberately excluded: the old enum also had 'another_appeal', but
 * that's a meta-status ("repeat contact"), not an actual service — that
 * concept is instead `leads.application_type` in the new system.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_service_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->text('comment')->nullable();
            $table->timestamps();
        });

        $names = [
            'Вікна',
            'Балкони',
            'Вікна в котеджі',
            'Ремонт вікон',
            'Заміна склопакета',
            'Обшивка балкона',
            'Балкон з виносом',
            'Сітки',
            'Балкон під ключ',
            'Підвіконня',
            'Внутрішні ролети',
        ];

        $now = now();

        DB::table('lead_service_types')->insert(array_map(
            fn (string $name) => ['name' => $name, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            $names,
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_service_types');
    }
};
