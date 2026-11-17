{{-- Admin/system version of the stages doc. KEEP IN SYNC with docs/STAGES.md (project documentation): change both together. --}}
@php
    $wrap = 'max-width:68rem;margin:0 auto;padding:.5rem 1rem 2.5rem;max-height:calc(100vh - 11rem);overflow-y:auto;';
    $h = 'font-size:1.2rem;font-weight:700;margin:1.8rem 0 .6rem;padding-bottom:.35rem;border-bottom:2px solid #2874A6;color:#1b4f72;';
    $p = 'font-size:.98rem;line-height:1.65;margin:.45rem 0;';
    $card = 'border:1px solid #cfd8de;border-radius:6px;padding:.9rem 1.1rem;background:#fff;';
    $th = 'padding:.5rem .6rem;border:1px solid #cfd8de;background:#eaf2f8;text-align:left;font-size:.85rem;';
    $td = 'padding:.5rem .6rem;border:1px solid #cfd8de;vertical-align:top;font-size:.92rem;';
    $auto = 'display:inline-block;padding:1px 8px;border-radius:10px;font-size:.78rem;background:#d5f5e3;color:#1e8449;font-weight:600;';
    $manual = 'display:inline-block;padding:1px 8px;border-radius:10px;font-size:.78rem;background:#fdebd0;color:#b9770e;font-weight:600;';
    $later = 'display:inline-block;padding:1px 8px;border-radius:10px;font-size:.78rem;background:#eaecee;color:#566573;font-weight:600;';
    $chip = 'display:inline-block;padding:4px 12px;border:1px solid #2874A6;background:#eaf2f8;color:#1b4f72;font-size:.85rem;border-radius:3px;';
    $arrow = 'color:#7f8c8d;margin:0 .3rem;';
@endphp
<div style="{{ $wrap }}">

    <div style="{{ $card }}background:#f4f8fb;">
        <strong>Коротко.</strong> Раніше «етап» був одним полем, яке перезаписувалось. Тепер кожен крок, що відбувся, фіксується окремим записом
        у <strong>журналі кроків</strong> (таблиця <code>process_steps</code>), а поточний етап лише відображає останній пройдений крок.
        Автоматика рухає етап <strong>тільки вперед</strong>; менеджер завжди може змінити його вручну (це теж запис у журналі).
        Документ для адміністратора й розробки; версію для менеджерів напишемо пізніше.
    </div>

    <div style="{{ $h }}">1. Воронка заявки</div>
    <div style="line-height:2.4;">
        @foreach ($leadStages as $code => $label)
            <span style="{{ $chip }}">{{ $label }}</span>@unless ($loop->last)<span style="{{ $arrow }}">→</span>@endunless
        @endforeach
    </div>
    <p style="{{ $p }}">Результат заявки — окреме поле: <em>В роботі</em> / <em>Угода укладена</em> / <em>Втрачено</em> (з причиною).</p>

    <table style="border-collapse:collapse;width:100%;margin-top:.6rem;">
        <thead><tr>
            <th style="{{ $th }}">Крок</th><th style="{{ $th }}">Код</th><th style="{{ $th }}">Що означає «відбулося»</th><th style="{{ $th }}">Як фіксується</th><th style="{{ $th }}">Реалізація</th>
        </tr></thead>
        <tbody>
        <tr><td style="{{ $td }}">Новий</td><td style="{{ $td }}"><code>new</code></td><td style="{{ $td }}">заявка з'явилась</td><td style="{{ $td }}"><span style="{{ $later }}">пізніше</span></td><td style="{{ $td }}"><span style="display:inline-block;padding:1px 8px;border-radius:10px;font-size:.78rem;background:#eaecee;color:#566573;font-weight:600;">— ще ні</span></td></tr>
        <tr><td style="{{ $td }}">Контакт</td><td style="{{ $td }}"><code>contacted</code></td><td style="{{ $td }}">є контакт із клієнтом (визначення — уточнюємо)</td><td style="{{ $td }}"><span style="{{ $later }}">пізніше</span></td><td style="{{ $td }}"><span style="display:inline-block;padding:1px 8px;border-radius:10px;font-size:.78rem;background:#eaecee;color:#566573;font-weight:600;">— ще ні</span></td></tr>
        <tr><td style="{{ $td }}"><strong>Замір</strong></td><td style="{{ $td }}"><code>measurement_scheduled</code></td><td style="{{ $td }}">створено «Заявку на замір»</td><td style="{{ $td }}"><span style="{{ $auto }}">автоматично</span></td><td style="{{ $td }}"><span style="display:inline-block;padding:1px 8px;border-radius:10px;font-size:.78rem;background:#d5f5e3;color:#1e8449;font-weight:600;">✔ реалізовано</span></td></tr>
        <tr><td style="{{ $td }}">Заміряно</td><td style="{{ $td }}"><code>measurement_done</code></td><td style="{{ $td }}">замірник провів замір</td><td style="{{ $td }}"><span style="{{ $later }}">пізніше</span></td><td style="{{ $td }}"><span style="display:inline-block;padding:1px 8px;border-radius:10px;font-size:.78rem;background:#eaecee;color:#566573;font-weight:600;">— ще ні</span></td></tr>
        <tr><td style="{{ $td }}">КП</td><td style="{{ $td }}"><code>proposal_sent</code></td><td style="{{ $td }}">клієнт отримав розрахунок</td><td style="{{ $td }}"><span style="{{ $later }}">пізніше</span></td><td style="{{ $td }}"><span style="display:inline-block;padding:1px 8px;border-radius:10px;font-size:.78rem;background:#eaecee;color:#566573;font-weight:600;">— ще ні</span></td></tr>
        <tr><td style="{{ $td }}">Узгодження</td><td style="{{ $td }}"><code>negotiation</code></td><td style="{{ $td }}">йде узгодження</td><td style="{{ $td }}"><span style="{{ $later }}">пізніше</span></td><td style="{{ $td }}"><span style="display:inline-block;padding:1px 8px;border-radius:10px;font-size:.78rem;background:#eaecee;color:#566573;font-weight:600;">— ще ні</span></td></tr>
        <tr><td style="{{ $td }}"><strong>Замовлення</strong></td><td style="{{ $td }}"><code>closing</code></td><td style="{{ $td }}">із заявки створено замовлення</td><td style="{{ $td }}"><span style="{{ $auto }}">автоматично</span></td><td style="{{ $td }}"><span style="display:inline-block;padding:1px 8px;border-radius:10px;font-size:.78rem;background:#d5f5e3;color:#1e8449;font-weight:600;">✔ реалізовано</span></td></tr>
        </tbody>
    </table>

    <div style="{{ $h }}">2. Воронка замовлення</div>
    <div style="line-height:2.4;">
        @foreach ($orderStages as $code => $label)
            <span style="{{ $chip }}">{{ $label }}</span>@unless ($loop->last)<span style="{{ $arrow }}">→</span>@endunless
        @endforeach
    </div>
    <p style="{{ $p }}">Статус замовлення — окреме поле: <em>В роботі</em> / <em>Виконано</em> / <em>Відмінено</em>.</p>
    <table style="border-collapse:collapse;width:100%;margin-top:.6rem;">
        <thead><tr><th style="{{ $th }}">Крок</th><th style="{{ $th }}">Код</th><th style="{{ $th }}">Як фіксується</th><th style="{{ $th }}">Реалізація</th></tr></thead>
        <tbody>
        <tr><td style="{{ $td }}">Нове замовлення</td><td style="{{ $td }}"><code>new</code></td><td style="{{ $td }}"><span style="{{ $auto }}">автоматично</span> при створенні замовлення із заявки</td><td style="{{ $td }}"><span style="display:inline-block;padding:1px 8px;border-radius:10px;font-size:.78rem;background:#d5f5e3;color:#1e8449;font-weight:600;">✔ реалізовано</span></td></tr>
        <tr><td style="{{ $td }}"><strong>Замір</strong></td><td style="{{ $td }}"><code>measuring</code></td><td style="{{ $td }}"><span style="{{ $auto }}">автоматично</span>: якщо замір призначено ще в заявці (при створенні замовлення) або коли в замовленні вказано дату заміру</td><td style="{{ $td }}"><span style="display:inline-block;padding:1px 8px;border-radius:10px;font-size:.78rem;background:#d5f5e3;color:#1e8449;font-weight:600;">✔ реалізовано</span></td></tr>
        <tr><td style="{{ $td }}">Решта (В обробці … Закрите)</td><td style="{{ $td }}">—</td><td style="{{ $td }}"><span style="{{ $later }}">пізніше</span> (зараз вручну)</td><td style="{{ $td }}"><span style="display:inline-block;padding:1px 8px;border-radius:10px;font-size:.78rem;background:#eaecee;color:#566573;font-weight:600;">— ще ні</span></td></tr>
        </tbody>
    </table>

    <div style="{{ $h }}">3. Правила</div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(18rem,1fr));gap:.8rem;">
        <div style="{{ $card }}"><strong>Лише вперед.</strong><p style="{{ $p }}">Автоматика ніколи не повертає етап назад: якщо заявка вже на «Заміряно», «КП» чи «Замовлення», створення заміру її не зсуває.</p></div>
        <div style="{{ $card }}"><strong>Крок записується один раз.</strong><p style="{{ $p }}">Повторна дія (другий замір) не дублює запис про крок. Крок можуть пропустити: замовлення без заміру просто не має кроку «замір».</p></div>
        <div style="{{ $card }}"><strong>Ручна зміна — теж запис.</strong><p style="{{ $p }}">Якщо менеджер вручну ставить етап, у журнал пишеться крок із позначкою «вручну» та його іменем.</p></div>
        <div style="{{ $card }}"><strong>Старі дані.</strong><p style="{{ $p }}">Для всіх наявних заявок і замовлень записано один крок — їхній поточний етап — без дати («зі старої системи»).</p></div>
    </div>

    <div style="{{ $h }}">4. Що пишеться в журнал</div>
    <p style="{{ $p }}">Один рядок = один крок: заявка/замовлення, код кроку, стан (<code>done</code>; <code>planned</code> і <code>cancelled</code> зарезервовані), джерело (<span style="{{ $auto }}">auto</span> <span style="{{ $manual }}">manual</span> <span style="{{ $later }}">legacy</span>), користувач, час, коментар.</p>

    <div style="{{ $h }}">5. Навіщо це потрібно</div>
    <ul style="{{ $p }}padding-left:1.4rem;list-style:disc;">
        <li><strong>Воронка продажів:</strong> скільки заявок дійшло до кожного кроку, скільки днів між кроками.</li>
        <li><strong>Реклама:</strong> конверсії Google Ads за фактом кроку, а не за позицією етапу.</li>
        <li><strong>Автоматичні дії:</strong> нагадування, листи клієнту, ескалації запускаються подією кроку.</li>
        <li><strong>Контроль менеджерів:</strong> хто й коли довів заявку до кроку.</li>
        <li><strong>Підказки менеджеру:</strong> прострочені заплановані кроки, заявки без руху.</li>
    </ul>

    <div style="{{ $h }}">6. Пріоритети та що далі</div>
    <p style="{{ $p }}">Головний акцент — <strong>заявки</strong> і воронки (найскладніше). Замовлення — пізніше й простіше: там більше автоматичного перемикання, ніж аналітики. Далі: по одному визначаємо решту кроків і те, що їх фіксує; потім підключаємо воронку, рекламу, підказки та ескалації.</p>

    <div style="{{ $h }}">7. Для розробки</div>
    <ul style="{{ $p }}padding-left:1.4rem;list-style:disc;">
        <li>Запис кроку й рух етапу: <code>App\Services\Leads\ProcessSteps::reach()</code> → <code>StageAdvancer::advance()</code>.</li>
        <li>Журнал: модель <code>ProcessStep</code>, трейт <code>HasProcessSteps</code> (Lead, Order).</li>
        <li>Синхронізація зі старою CRM (<code>sync:legacy</code>) оновлює етапи за старими даними й може перезаписати їх у локальній базі; журнал їх не відновлює.</li>
        <li>Проєктна документація: <code>docs/STAGES.md</code> — змінювати разом із цим вікном.</li>
    </ul>

    <div style="{{ $h }}">8. Google Ads: що не можна зламати <span style="{{ $manual }}">пріоритет №1</span></div>
    <p style="{{ $p }}">Передача заявок і продажів у Google (<code>conversion_outbox</code>, <code>conversions:run</code> кожні 15 хв) важливіша за будь-яку автоматику етапів. Етапи, результат і суми — її вхідні дані.</p>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(22rem,1fr));gap:.8rem;">
        <div style="{{ $card }}"><strong>Кваліфікована заявка</strong>
            <ul style="{{ $p }}padding-left:1.3rem;list-style:disc;">
                <li>Рішення завжди за <strong>етапом і результатом</strong>: не йде, якщо «Втрачено» або етап «Новий». <strong>Вихід із «Новий» = конверсія.</strong></li>
                <li>Старі статуси з синхронізації вже переведені в етап/результат, тому це однаково працює для лідів зі старої CRM і для нових.</li>
                <li>Рекламація (тип послуги «Рекламація») не йде.</li>
                <li>Потрібні: gclid або хеш телефону/email, не старше 90 днів, джерело — сайт чи дзвінок.</li>
            </ul>
        </div>
        <div style="{{ $card }}"><strong>Продаж</strong>
            <ul style="{{ $p }}padding-left:1.3rem;list-style:disc;">
                <li>Перше замовлення ліда, статус не «Відмінено», сума більша за 0.</li>
                <li>Значення = сума замовлення. Змінилась після відправки — повторна відправка з новою сумою.</li>
            </ul>
        </div>
        <div style="{{ $card }}"><strong>Механіка</strong>
            <ul style="{{ $p }}padding-left:1.3rem;list-style:disc;">
                <li>Одна кваліфікована подія й один продаж на лід, дублів немає.</li>
                <li>Буфер 50 хв: якщо стан повернули назад до відправки — подію скасовано.</li>
                <li><strong>Вже відправлене назад не відкликається.</strong></li>
                <li>Час події = момент постановки в чергу.</li>
            </ul>
        </div>
        <div style="{{ $card }}border-color:#e6b0aa;background:#fdf2f0;"><strong>Правила для змін</strong>
            <ul style="{{ $p }}padding-left:1.3rem;list-style:disc;">
                <li>Не перейменовувати коди <code>new</code>, <code>lost</code>, <code>cancelled</code>, поля <code>total_price</code>, <code>legacy_status</code> без правки <code>ConversionPlanner</code> і <code>AudienceClassify</code>.</li>
                <li>Автокрок, що виводить заявку з «Новий», створює конверсію: зсувати лише за реальної кваліфікації.</li>
                <li>Автоматика не ставить «Втрачено» й не повертає на «Новий» без причини.</li>
                <li>Не змінювати автоматично типи послуг ліда й суму замовлення; не втрачати gclid, джерело, контакти, дату створення.</li>
                <li>Журнал кроків поки не впливає на конверсії; перехід на конверсію за кроком — окрема обережна зміна з новим baseline.</li>
            </ul>
        </div>
    </div>
    <p style="{{ $p }}"><strong>Перехід на нову ERM:</strong> окремий перемикач не потрібен. Коли синхронізацію зі старою CRM вимкнуть, конверсії й далі йдуть за етапом і результатом. Що прибрати після переходу — <code>docs/LEGACY-CLEANUP.md</code> (пошук: <code>LEGACY-CLEANUP</code>).</p>
    <p style="{{ $p }}">Перед і після змін: <code>php artisan conversions:status</code>; локально не запускати <code>conversions:run</code>, не перевіривши <code>conversions:mode</code>. Повний текст: <code>docs/STAGES.md</code>, розділ «Google Ads: що не можна зламати».</p>
</div>
