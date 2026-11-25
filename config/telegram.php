<?php

/*
 * Telegram-бот ERM (безінтерфейсний модуль). Той самий бот, що раніше відправляв заявки з сайту.
 * Токен і чат — лише в .env (у git і в документах не писати). Документація: docs/modules/telegram.md.
 */
return [
    // Головний вимикач. Поки false, TelegramSender нічого не відправляє (локально за замовчуванням вимкнено).
    'enabled' => (bool) env('TELEGRAM_ENABLED', false),

    'token' => env('TELEGRAM_BOT_TOKEN'),

    // Чат за замовчуванням (група менеджерів/контрольний чат), якщо чат не вказано явно.
    'default_chat' => env('TELEGRAM_DEFAULT_CHAT_ID'),

    // Таймаут запиту до api.telegram.org, секунди.
    'timeout' => (int) env('TELEGRAM_TIMEOUT', 5),
];
