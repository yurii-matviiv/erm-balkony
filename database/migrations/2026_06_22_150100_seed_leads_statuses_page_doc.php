<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the "Статуси заявок" page doc for the Leads list page — the
 * explanation worked out with the user about how stage/status/lost
 * reason map onto what used to be one old, messy `status` field. First
 * real content in the new "Документація сторінки" system (see
 * create_page_docs_table). Editable afterwards from the page itself by
 * anyone with the super_admin or founder role — this is just the
 * starting point, not meant to be the final word.
 */
return new class extends Migration
{
    public function up(): void
    {
        $content = <<<'HTML'
            <h3>Етап і Статус — це два різних поля</h3>
            <p><strong>Етап</strong> — де зараз клієнт у процесі продажу (рухається вперед). <strong>Статус</strong> — чи угода ще в роботі, виграна чи втрачена. <strong>Причина втрати</strong> — пояснення, чому саме втрачена (заповнюється тільки якщо статус "Втрачено").</p>

            <h3>Етапи воронки</h3>
            <ul>
                <li><strong>Новий лід</strong> — заявка щойно створена, ще не обробляли.</li>
                <li><strong>Контакт встановлено</strong> — зв'язались з клієнтом (сюди ж: дзвінок в обробці, візит в офіс, домовились зв'язатись пізніше).</li>
                <li><strong>Замір призначено</strong> — домовились про дату заміру.</li>
                <li><strong>Замір проведено</strong> — замірник вже був на об'єкті.</li>
                <li><strong>КП надіслано</strong> — комерційну пропозицію відправили клієнту.</li>
                <li><strong>Узгодження умов</strong> — обговорюємо ціну/умови.</li>
                <li><strong>Оформлення угоди</strong> — фінальний крок перед "Угода укладена".</li>
            </ul>

            <h3>Статуси</h3>
            <ul>
                <li><strong>В роботі</strong> — звичний стан для активного ліда.</li>
                <li><strong>Угода укладена</strong> — клієнт погодився, продаж відбувся.</li>
                <li><strong>Втрачено</strong> — далі не працюємо з цим лідом. Обов'язково вказати причину.</li>
            </ul>

            <h3>Типові причини втрати (вільний текст у полі "Причина втрати")</h3>
            <ul>
                <li>Нецільовий лід</li>
                <li>Інше місто обслуговування</li>
                <li>Скасовано клієнтом</li>
                <li>Пропущено (не вдалось додзвонитись)</li>
            </ul>

            <blockquote>
                <p><strong>Важливо:</strong> "Рекламація" — це НЕ причина втрати і не статус. Це тип звернення (та сама графа, де "Вікна", "Балкони" тощо) — клієнт звертається зі скаргою на вже встановлене вікно. Такий лід зазвичай лишається в статусі "В роботі", просто з типом звернення "Рекламація".
            </blockquote>

            <p><em>Це робоча версія — якщо щось працює не так, як тут написано, або з'явились нові ситуації, довідку можна і потрібно оновлювати прямо тут.</em></p>
            HTML;

        DB::table('page_docs')->insert([
            'page_key' => 'leads',
            'section_key' => 'statuses',
            'title' => 'Статуси заявок',
            'content' => $content,
            'sort_order' => 0,
            'updated_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('page_docs')->where('page_key', 'leads')->where('section_key', 'statuses')->delete();
    }
};
