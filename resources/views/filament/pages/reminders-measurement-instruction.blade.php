@php
    $evening = $cfg['evening_before_at'];
    $hrs = $cfg['day_of_hours_before'];
    $earliest = $cfg['day_of_earliest'];
    $every = $cfg['repeat_interval_minutes'];
    $max = $cfg['max_repeats'];
    $h = 'font-size:1.15rem;font-weight:600;margin:1.4rem 0 .5rem;';
    $p = 'font-size:1rem;line-height:1.6;margin:.4rem 0;';
@endphp
<div style="max-width:60rem;margin:0 auto;padding:.5rem 1rem 2rem;max-height:calc(100vh - 11rem);overflow-y:auto;">
    <p style="{{ $p }}">Коли менеджер створює в заявці «Заміри» запис на замір, система сама створює для нього <strong>два нагадування</strong> і надсилає їх push-сповіщенням (так само, як звичайні нагадування по заявках). Усі години — за київським часом.</p>

    <div style="{{ $h }}">1. Які нагадування створюються</div>
    <table style="border-collapse:collapse;width:100%;font-size:.95rem;">
        <thead><tr style="background:#f4f8fb;text-align:left;">
            <th style="padding:.5rem;border:1px solid #cfd8de;">Нагадування</th>
            <th style="padding:.5rem;border:1px solid #cfd8de;">Коли спрацьовує</th>
        </tr></thead>
        <tbody>
        <tr><td style="padding:.5rem;border:1px solid #cfd8de;">Ввечері напередодні</td>
            <td style="padding:.5rem;border:1px solid #cfd8de;">О <strong>{{ $evening }}</strong> дня перед заміром: «Завтра замір…».</td></tr>
        <tr><td style="padding:.5rem;border:1px solid #cfd8de;">У день заміру</td>
            <td style="padding:.5rem;border:1px solid #cfd8de;">За <strong>{{ $hrs }} год</strong> до часу заміру, але не раніше <strong>{{ $earliest }}</strong>. Якщо час заміру не вказано — рівно о {{ $earliest }}. Якщо замір о {{ $earliest }} або раніше — нагадування в день заміру не створюється (достатньо вечірнього).</td></tr>
        </tbody>
    </table>
    <p style="{{ $p }}">У тексті — клієнт, адреса заміру і замірник. Нагадування отримує <strong>менеджер заявки</strong>.</p>

    <div style="{{ $h }}">2. Якщо нагадування вже «в минулому»</div>
    <p style="{{ $p }}">Час, що вже минув на момент створення заміру, пропускається. Якщо минули обидва, а сам замір ще попереду — одне нагадування прийде приблизно за хвилину.</p>

    <div style="{{ $h }}">3. Повтори, якщо менеджер не відреагував</div>
    <p style="{{ $p }}">Push приходить у момент нагадування. Якщо в цій вкладці не натиснути <strong>«Побачив»</strong> (і не позначити «Виконано»), він повторюється кожні <strong>{{ $every }} хв</strong>, ще не більше <strong>{{ $max }} разів</strong>. Після «Побачив» повтори припиняються.</p>

    <div style="{{ $h }}">4. Що бачить менеджер</div>
    <ul style="{{ $p }}padding-left:1.4rem;list-style:disc;">
        <li>Спливаюче push-сповіщення (потрібно дозволити сповіщення через шестерню ⚙ на цій сторінці; браузер має бути запущений).</li>
        <li>Червоний лічильник на вкладці «Заміри» та червоний рядок угорі сторінки, поки є нагадування без «Побачив».</li>
    </ul>

    <div style="{{ $h }}">5. Важливо знати</div>
    <ul style="{{ $p }}padding-left:1.4rem;list-style:disc;">
        <li>Нагадування створюються в момент створення заміру. Якщо потім змінити дату заміру — вручну відредагуй час нагадування (олівець у рядку).</li>
        <li>Якщо в заявки немає менеджера — нагадування не створюється.</li>
        <li>Нагадування старші за добу не надсилаються.</li>
    </ul>
</div>
