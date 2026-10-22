<?php

/*
 | Customer Match: список клієнтів ERM -> Google Ads через Data Manager API.
 | Нічого не відправляється в Google, поки в .env не стоїть CUSTOMER_MATCH_CONFIRMED=true
 | (ручне підтвердження власника: умови Customer Match прийняті, згода клієнтів є).
 */
return [
    'confirmed' => (bool) env('CUSTOMER_MATCH_CONFIRMED', false),

    // Максимум Google на запит: 10 000 учасників.
    'batch_size' => 5000,

    // Скільки днів учасник лишається в списку (макс. 540).
    'membership_days' => 540,

    // Сегменти: qualified/unqualified беруться з clients.ad_status (команда audience:classify),
    // buyers — клієнти з замовленням.
    'segments' => [
        'qualified' => [
            'name' => 'ERM: кваліфіковані клієнти',
            'description' => 'Клієнти, у яких є кваліфікована заявка або замовлення (автоматично з ERM)',
        ],
        'unqualified' => [
            'name' => 'ERM: некваліфіковані клієнти',
            'description' => 'Клієнти лише з новими або сміттєвими заявками (автоматично з ERM)',
        ],
        'buyers' => [
            'name' => 'ERM: покупці',
            'description' => 'Клієнти, у яких є хоча б одне замовлення (автоматично з ERM)',
        ],
    ],
];
