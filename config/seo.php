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

    // Перспективні слова: усі запити сайту зі знімка, відсортовані за потенціалом кліків.
    'prospect_min_impressions' => 20, // слова з меншою кількістю показів не показуємо (крім закріплених)
    'prospect_target_position' => 3,  // до якої позиції рахуємо потенціал
    // Автоматично виключаються слова, де є і «дія», і «об’єкт» (підрядок, без регістру). Видалене вручну не повертається.
    'exclude_rules' => [
        // Правило з ключем «any»: достатньо одного підрядка.
        'wardrobes' => ['any' => ['шаф', 'шкаф']],
        // Лише міжкімнатні перегородки: потрібне і «міжкімнатн…», і «перегород…» (офісні, балконні тощо не чіпаємо).
        'room_partitions' => [
            'action' => ['міжкімнат', 'межкомнат'],
            'object' => ['перегород'],
        ],
        'repair_windows' => [
            'action' => ['ремонт', 'регулюв', 'регулир', 'регулів', 'відремонт', 'отремонт', 'відрегулю', 'отрегулир', 'регулировщ', 'відрегул'],
            'object' => ['вікн', 'вікон', 'окон', 'окна', 'окно', 'фурніт', 'фурнит', 'євровік', 'евроокн', 'евроок', 'стеклопакет', 'склопакет', 'пвх', 'рехау', 'rehau', 'створк', 'створц'],
        ],
    ],
    // Закріплені слова: завжди в знімку й у вкладці, навіть при малій кількості показів.
    'pinned_queries' => [
        'балкон під ключ хрущовка',
    ],
    // Орієнтовний CTR за позицією (для потенціалу); ≥11 — запасне значення.
    'ctr_benchmark' => [1 => 0.28, 2 => 0.15, 3 => 0.10, 4 => 0.07, 5 => 0.05, 6 => 0.04, 7 => 0.03, 8 => 0.025, 9 => 0.02, 10 => 0.018, 'rest' => 0.008],

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
