<?php

/*
 * Сповіщення в Telegram про незакриті пропущені дзвінки (Binotel). Документація: docs/modules/missed-calls.md.
 * Усі значення можна змінити в .env; значення тут — бойові.
 */
return [
    // Працює лише на сервері (Linux), на комп'ютері розробки (Windows/Laragon) мовчить. Нових рядків у .env НЕ потрібно:
    // на етапі розробки .env копіюється на хостинг повністю, тому прапорець у .env був би однаковим і там, і тут.
    // MISSED_CALLS_ENABLED у .env лише для примусового перевизначення (в .env, що копіюється на хостинг, не ставити).
    'enabled' => filter_var(env('MISSED_CALLS_ENABLED', PHP_OS_FAMILY !== 'Windows'), FILTER_VALIDATE_BOOLEAN),

    // Робочі години за Києвом: відправка, коли година від ... до ... включно (09:00 ... 18:00).
    'hour_from' => (int) env('MISSED_CALLS_HOUR_FROM', 9),
    'hour_to' => (int) env('MISSED_CALLS_HOUR_TO', 18),

    // Як часто відправляти, хвилини (планувальник запускає команду кожні 5 хв, команда сама тримає інтервал).
    'interval_minutes' => (int) env('MISSED_CALLS_INTERVAL_MINUTES', 60),

    // Мітки Binotel, за якими дзвінок не показувати (через кому, нижній регістр). Порожньо = показувати все.
    'ignore_labels' => array_filter(array_map('trim', explode(',', mb_strtolower((string) env('MISSED_CALLS_IGNORE_LABELS', ''))))),

    // Базова адреса ERM для посилань у повідомленні (завжди бойова, навіть коли команда запущена локально).
    'erm_url' => rtrim((string) env('MISSED_CALLS_ERM_URL', 'https://erm.balkony-vikna.kyiv.ua'), '/'),

    'timezone' => 'Europe/Kyiv',
];
