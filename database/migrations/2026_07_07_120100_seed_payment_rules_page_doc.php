<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Initial in-admin documentation for the "Платіжні правила" page — the
 * admin/developer point of truth requested by the user. Content is a
 * short mirror of CLAUDE.md "Платежі — принципи"; super_admin/founder
 * can edit it further right on the page (PageDoc module).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('page_docs')->where('page_key', 'payment-rules')->where('section_key', 'principles')->exists()) {
            return;
        }

        DB::table('page_docs')->insert([
            'page_key' => 'payment-rules',
            'section_key' => 'principles',
            'title' => 'Принципи роботи з платежами',
            'content' => '<h2>Точка істини</h2><p>Цей блок — стисле дзеркало розділу «Платежі — принципи» у технічній документації проєкту (CLAUDE.md). Зміна логіки платежів починається зі зміни принципів, потім — код.</p><h3>Чотири принципи</h3><ol><li><strong>Жодних зашитих цифр.</strong> Усі фінансові показники рахуються виключно з записів платежів.</li><li><strong>Перенесення ≠ копіювання.</strong> Старі платежі розкладаються в нову структуру за правилами; що не розклалось — черга «Не розібрані»; свідомо непотрібне — «Анульовані» (историю видно, у підсумки не входить).</li><li><strong>Нові платежі народжуються правильними.</strong> Повна структура одразу при створенні; коментар — лише довідковий текст.</li><li><strong>Авторство.</strong> Кожен платіж має «хто вніс», включно з історичними.</li></ol><h3>Дві системи на цій сторінці</h3><p><strong>Міграція зі старої CRM</strong> — разовий/паралельний імпорт: як старі поля лягають у нові (таблиці вище).</p><p><strong>Правила нової системи</strong> — редаговані правила автоматичного розподілу банківських транзакцій (таблиця нижче). Використовуватимуться майбутнім імпортом виписок ПриватБанку; транзакція без правила потрапляє в «Нез\'ясовані» на ручний розбір.</p>',
            'sort_order' => 0,
            'updated_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('page_docs')
            ->where('page_key', 'payment-rules')
            ->where('section_key', 'principles')
            ->delete();
    }
};
