<?php

/*
 | Offline conversions CRM -> Google Ads (Data Manager API).
 | Runtime switches (mode, buffer) live in app_settings, see ConversionSettings.
 */
return [
    // Minutes a planned conversion waits before sending (default; override in app_settings).
    'buffer_minutes' => 50,

    // Legacy lead statuses that are junk and never become a conversion.
    'junk_statuses' => ['not_targeted', 'another_city', 'canceled', 'reklamatsiya_amtech', 'propushcheno'],

    // lead.source -> Google Ads conversion action id (same actions the old CRM used).
    'qualified_actions' => [
        'site' => '7061497668',  // form_callback_offline
        'call' => '7061474595',  // binotel_ct_call_received_offline
    ],

    // Conversion action for actual sales (new UPLOAD_CLICKS action, created in Google Ads).
    // Empty = sale events are not created.
    'sale_action_id' => env('CONVERSIONS_SALE_ACTION_ID'),

    'currency' => 'UAH',
    'timezone' => 'Europe/Kyiv',

    // Google click lookback of the actions is 90 days.
    'lookback_days' => 90,

    // Value of a qualified lead = expected revenue by service type (UAH),
    // see claude/conversion-value-analysis-2026-10-20.md. Highest of the lead's types wins.
    'default_value' => 5000,
    'weights' => [
        'Вікна' => 8800,
        'Балкони' => 11700,
        'Вікна в котеджі' => 12500,
        'Балкон з виносом' => 15400,
        'Обшивка балкона' => 4500,
        'Ремонт вікон' => 8700,
        'Заміна склопакета' => 6900,
        'Балкон під ключ' => 20900,
    ],

    'batch_size' => 200,
    'max_attempts' => 5,

    // Events per Data Manager request. 1 = requestStatus is exact per event (recommended).
    'events_per_request' => 1,
];
