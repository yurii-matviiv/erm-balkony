<?php

/*
 * SEO-моніторинг позицій через Google Search Console API (лише читання).
 * Інструкція: «Інструменти підключення - Балкони та вікна/services/google-search-console.md».
 */
return [
    'site_url' => env('GOOGLE_SC_SITE_URL', 'https://balkony-vikna.kyiv.ua/'),

    // Шлях до ключа service account. Сам ключ не копіювати й не друкувати.
    'key_path' => env('GOOGLE_SC_KEY_PATH', storage_path('app/google/storied-yew-509910-v2-039ced771a2c.json')),

    'window_days' => 28,
    // Дані Search Console приходять із затримкою 2–3 дні.
    'lag_days' => 3,
    'top_limit' => 30,
    'new_page_queries_limit' => 10,

    // Пороги звіту змін.
    'position_change' => 3,        // позицій
    'impressions_change_pct' => 30, // %
    'min_impressions' => 10,        // менше — висновків не робимо

    // Нові сторінки (шляхи без домену). Російські пари для моделей — за шаблоном /ru/<slug>; звірити після першого знімка.
    'new_pages' => [
        '/vikna', '/ru/okna',
        '/vikna-salamander', '/ru/okna-salamander',
        '/vikna-openteck', '/ru/okna-openteck',
        '/salamander-proevolution-72', '/ru/salamander-proevolution-72',
        '/salamander-bluevolution-82', '/ru/salamander-bluevolution-82',
        '/salamander-bluevolution-92', '/ru/salamander-bluevolution-92',
        '/openteck-elit-70', '/ru/openteck-elit-70',
        '/openteck-pro-76', '/ru/openteck-pro-76',
    ],
];
