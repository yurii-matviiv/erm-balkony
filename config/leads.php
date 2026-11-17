<?php

return [
    /*
     | Часовий пояс, у якому записаний created_at ЛІДІВ ЗІ СТАРОЇ СИСТЕМИ (legacy_id не null): «настінний» час сервера
     | старої CRM, а не UTC. Підібрано за звіркою з дзвінками Binotel (17.11.2026: лід через 3–12 хв після дзвінка
     | виходить саме при +1 год взимку). Нові заявки (створені в новій ERM) лежать у UTC (app.timezone).
     | Використовується для швидкості реакції (FirstCallDetector). Див. docs/STAGES.md.
     */
    'legacy_timezone' => env('LEADS_LEGACY_TIMEZONE', 'Europe/Berlin'),
];
