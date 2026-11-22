# Інтеграції: мобільні оператори, Binotel, офлайн-конверсії Google Ads

> Частина документації ERM. Індекс і навігація: [CLAUDE.md](../../CLAUDE.md). Читай цей файл, лише якщо задача стосується теми вище.

## Модуль "Мобільні оператори" (інтеграція, Kyivstar першим)

Дзеркало патерну ПриватБанку: `mobile_operator_accounts` + `MobileOperatorAccount` (client_secret — `encrypted` cast) + `MobileOperatorIntegrationResource` (група "Інтеграції", модальний create/edit, сторінки List + View) + `App\Services\MobileOperators\KyivstarApiService`. Ідентифікація підключення — **оператор + номер телефону** (unique пара; явна вимога: завжди видно, звідки дані). Довідник операторів у моделі: kyivstar/vodafone/lifecell, драйвер є лише для Kyivstar.

**Kyivstar API:** My Kyivstar Business API на `https://b2b-api.kyivstar.ua`, НЕ публічний `api-gateway.kyivstar.ua`. OAuth2 client_credentials: `POST /idp/oauth2/token` через Basic Auth + `grant_type=client_credentials`, токен кешується до закінчення строку дії. Баланс читається з `GET /rest/billing-accounts`: `getBalance()` сумує `content[].balances.main.amount`, повертає UAH і кількість billing accounts. View-сторінка інтеграції показує технічний стан API, а керівницьке відображення поточного залишку винесене в "Аналітика рахунків" блоком "Баланс мобільних рахунків".

**Права** — стандартна default-deny схема: Resource → Shield-політика після `php artisan shield:generate --all --panel=admin`; нікому, крім super_admin, не видно, поки адмін не увімкне пункт у "Боковій панелі" (SidebarPermissionSync видасть ViewAny/View). Дії Create/Update — вручну на сторінці "Ролі", як завжди.

## Модуль "Binotel" (gateway-підключення)

Binotel реалізовано як окреме gateway-підключення в групі "Інтеграції", за тим самим патерном, що ПриватБанк і мобільні оператори: `binotel_accounts` + `BinotelAccount` (`api_secret` — `encrypted` cast) + `BinotelIntegrationResource` (модальний create/edit, сторінки List + View) + `App\Services\Binotel\BinotelApiService`. Це **не** імпорт дзвінків і не логіка лідів — це єдине місце, де зберігаються доступи до Binotel, щоб майбутні модулі могли повторно використовувати це підключення. Обов'язкові параметри підключення: `companyID`, `key`, `secret` — їх надає підтримка Binotel.

**Binotel API:** API v4 на `https://api.binotel.com/api/4.0`. REST API авторизується через POST JSON з `key` і `secret`; у нашому акаунті також зберігаємо `companyID`, бо він приходить у Binotel API PUSH подіях. Перевірка підключення на View-сторінці викликає `settings/list-of-employees.json`: успішний `status=success` означає, що ключі валідні й API доступний; додатково показується кількість працівників з `listOfEmployees`. Майбутній імпорт дзвінків/записів/аналітики має використовувати цей самий `BinotelApiService` і активний `BinotelAccount`, а не дублювати ключі в feature-коді.

**USSD-тест:** на View-сторінці Binotel є експериментальна дія "Перевірити USSD". Вона пробує `POST /pbx-numbers/send-ussd.json` з `companyID`, `key`, `secret`, `number`, `code` (для lifecell типовий `*111#`) і показує відповідь без збереження в БД. Перший реальний тест 2026-07-21 повернув від Binotel `status=error`, `code=102`, `message="No such method"`; в архівній офіційній документації `developers.binotel.ua` від 2024-05-20 немає розділу `pbx-numbers` або `send-ussd`. Наступний крок — уточнити в підтримки Binotel, чи існує приватний REST-метод для USSD/GSM-балансу, або підтвердити, що ця функція є лише в кабінеті.

**Права** — стандартна default-deny схема через Filament Shield. Після додавання ресурсу обов'язково `php artisan shield:generate --all --panel=admin`; на хостингу це робить GitHub Actions після `git push hosting main`.

## Офлайн-конверсії Google Ads (Data Manager API) — outbox

Нова ERM сама передає конверсії в Google Ads замість старого скрипта `update_conversion_data.php` старої CRM. Код: `app/Services/Conversions/*`, модель `ConversionOutbox` (таблиця `conversion_outbox`), `config/conversions.php`, команди `conversions:mode|baseline|run|status`, сторінка "Інтеграції → Конверсії Google Ads" (`ConversionOutboxResource`). Пише ТІЛЬКИ в нову БД; стара CRM лише читається (на хостингу `LEGACY_DB_*` = користувач `balkonio_ro`, права лише SELECT).

- **Режими** (`app_settings.conversions_mode`): `off` (за замовчуванням, планувальник нічого не робить) → `validate` (черга збирається, у Google йде лише `validateOnly`) → `live`. Перемикач на сторінці конверсій; `live` вимагає одноразового `conversions:baseline` (позначає все, що вже передав старий скрипт, як skipped/baseline, щоб не слати вдруге).
- **Коли:** "кваліфікована заявка" — `legacy_status` змінився з `new` на не-сміттєвий (сміттєві: not_targeted, another_city, canceled, reklamatsiya_amtech, propushcheno), є чим зіставити в Google — gclid і/або телефон/email клієнта (розширені конверсії для лідів: `userData` з SHA-256 від E.164-телефону, `UserDataBuilder`; без gclid лід іде лише з userData), `source` = site або call. "Продаж" — є замовлення з `lead_id` і `total_price > 0`, не cancelled; дія створена вручну/API: `erm_sale_offline` (категорія Purchase, вторинна, не впливає на ставки), ID у `.env` `CONVERSIONS_SALE_ACTION_ID` (на сервері `.env` правиться ТІЛЬКИ вручну користувачем, ніколи не деплоїться).
- **Буфер 50 хв** (`conversions_buffer_minutes`): рядок чекає `send_after`, при зміні стану/суми таймер перезапускається, перед відправкою стан перевіряється ще раз; якщо менеджер помилився й виправив — відправка скасовується (`changed_before_send`).
- **gbraid/wbraid**: дії ONE_PER_CLICK їх не приймають → вони не передаються, такий лід іде лише з userData (телефон). Без gclid і без валідного телефону/email → `skipped(no_identifier)`. Сирі телефон/email у outbox не зберігаються, хеш рахується при відправці.
- **Увага при зміні правил відбору:** після деплою такої зміни спершу режим `off`, потім `conversions:baseline`, і лише тоді `validate` — інакше sync постав у чергу всю історію за 90 днів.
- **Вага** кваліфікованої заявки = очікувана виручка за типом послуги (`config/conversions.php`, джерело `claude/conversion-value-analysis-2026-10-20.md` у проєкті).
- **Авторизація:** service account з `GoogleAdsAccount`, scope `https://www.googleapis.com/auth/datamanager` (з `adwords` — 403). Стан статусу запитів: `requestStatus:retrieve`.
- Баг, який було знайдено: `LeadsSyncMapper` читав `gdid` замість `gclid` (у новій ERM не було жодного gclid) — виправлено.
- Розгорнуто на хостингу 2026-10-20 (режим `validate`; `.env` правиться лише вручну).
