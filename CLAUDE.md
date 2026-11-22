# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

> **Це ІНДЕКС документації, а не вся документація.** Не читай усе: знайди в дереві нижче розділ, потрібний для задачі, і відкрий лише його. Нова інформація (рішення, причини, статус, плани) дописується в **відповідний файл дерева**, а не сюди; сюди — лише новий рядок в індексі, якщо з'явився новий файл. Не переписуй код — лише те, що не видно з самого коду.

## Що це за проєкт

ERM — нова система для компанії balkony-vikna.kyiv.ua (вікна/балкони), яка замінює стару самописну PHP CRM ("balkonio_erm"). Стара система працює і досі використовується в продакшені — заміна відбувається поетапно:

1. Новий застосунок будується і тестується локально, працюючи зі своєю **власною** базою даних.
2. Стара база даних — **тільки джерело для одноразового перенесення даних** (users, clients, suppliers, candidates, ...), доступна через окреме read-only з'єднання `legacy`. Новий код НІКОЛИ не пише в це з'єднання.
3. На етапі деплою: піднімається чиста production-база нового застосунку, у `.env` змінюються `LEGACY_DB_*` на реальні продакшен-креди старої CRM (це лише env, без змін коду), і через адмінку "Синхронізація" заново проганяються всі мапери — це наповнює реальну production-базу.
4. Планується ~тиждень паралельної роботи обох систем, після чого стара БД повністю виводиться з експлуатації.

## Команди

```bash
php artisan serve                                   # локальний сервер (або Laragon vhost erm.balkony-vikna.kyiv.ua.test)
php artisan migrate                                 # застосувати міграції
php artisan shield:generate --all --panel=admin      # перегенерувати права/політики Filament Shield — ОБОВ'ЯЗКОВО після додавання нового Resource/Page
php artisan shield:super-admin                       # призначити роль super_admin користувачу
vendor/bin/pint                                      # форматування коду (Laravel Pint)
composer test                                        # php artisan config:clear + php artisan test
php artisan sync:legacy                             # ручний запуск повної синхронізації зі старої БД (всі мапери)
php artisan schedule:work                           # локальне тестування планувальника (тримає процес, кожну хвилину)
```

Команди виконує користувач у своєму терміналі (Windows PowerShell в Cowork-сесії дозволений лише на клік, без вводу тексту) — Claude дає точну команду, користувач її запускає і присилає вивід.

> Усі фонові завдання (cron → `schedule:run` → artisan-команди), правила та чекліст додавання нового завдання: **`docs/SCHEDULER.md`**. Не вигадувати окремих cron-скриптів — додавати команду в `routes/console.php`.

## Дерево документації (читай лише потрібне)

**Перш ніж робити будь-яку сторінку чи блок адмінки — прочитай [docs/ui/standards.md](docs/ui/standards.md)**: адмінка будується зі стандартних елементів Filament; власна розмітка лише коли потрібного елемента немає. Там же реєстр єдиного вигляду (вкладки, бар дат, вибір знімка, кнопки).

### Вигляд і елементи — `docs/ui/`
| Файл | Коли читати |
|---|---|
| [ui/standards.md](docs/ui/standards.md) | **Завжди перед новою/зміненою сторінкою:** що брати з Filament, порядок блоків, реєстр стандартів вигляду, чекліст |
| [ui/date-filter.md](docs/ui/date-filter.md) | Таблиця або аналітика з періодом (бар «Період / З / По») |
| [ui/general-look.md](docs/ui/general-look.md) | Колір, радіуси, хлібні крихти, меню менеджера, згортання сайдбара, домашня сторінка |
| [ui/forms-cards.md](docs/ui/forms-cards.md) | Форми, картки ліда/замовлення, статус-бар, `ig-card`, верх сторінки |
| [ui/page-docs.md](docs/ui/page-docs.md) | Довідка прямо на сторінці (PageDoc) |
| [INSTRUCTIONS.md](docs/INSTRUCTIONS.md) | Кнопка «Інструкція» (повноекранна довідка блока) |

### Архітектура — `docs/arch/`
| Файл | Коли читати |
|---|---|
| [arch/sync-legacy.md](docs/arch/sync-legacy.md) | Синхронізація зі старою CRM, мапери, `legacy_id`, міст «Постачальники» → стара CRM, розділення Client/Supplier/Candidate |
| [arch/hosting.md](docs/arch/hosting.md) | Хостинг, деплой, crontab (⚠️ `schedule:run` обов'язковий після переїзду) |
| [arch/access-roles-panels.md](docs/arch/access-roles-panels.md) | Ролі, права (Shield), бокова панель, дві панелі (admin/manager), вхід під користувачем |
| [arch/conventions.md](docs/arch/conventions.md) | Патерни ресурсів, правила переносу зі старої CRM, UX-принципи, шрифти, зображення |
| [SCHEDULER.md](docs/SCHEDULER.md) | Фонові завдання і розклад (`routes/console.php`) |
| [LEGACY-CLEANUP.md](docs/LEGACY-CLEANUP.md) | Що прибрати після відключення старої CRM |

### Модулі — `docs/modules/`
| Файл | Коли читати |
|---|---|
| [modules/leads.md](docs/modules/leads.md) | Заявки (Lead), картка ліда, клієнт vs лід, `auto_address` |
| [modules/orders.md](docs/modules/orders.md) | Замовлення (Order) |
| [modules/finance.md](docs/modules/finance.md) | Платежі, взаєморозрахунки, аналітика рахунків, З/П (див. також [INVOICE-ANALYTICS.md](docs/INVOICE-ANALYTICS.md)) |
| [modules/integrations.md](docs/modules/integrations.md) | Мобільні оператори, Binotel, офлайн-конверсії Google Ads |
| [modules/recruitment.md](docs/modules/recruitment.md) | Форма набору персоналу, кандидати, аналітика форми |
| [modules/marketing.md](docs/modules/marketing.md) | Експорт лідів для агентства, **SEO: позиції Google (Search Console)** |
| [modules/dashboard.md](docs/modules/dashboard.md) | Дашборд |
| [STAGES.md](docs/STAGES.md) | Автоматизація етапів і статусів заявок/замовлень |
| [STATUS.md](docs/STATUS.md) | Загальний стан проєкту, що зроблено / що в плані |


### Покажчик: де тепер розділи, на які посилаються коментарі в коді
«Платежі — принципи», «Модуль Взаєморозрахунки», «Аналітика рахунків», «Модуль З/П» → `docs/modules/finance.md` · «Замовлення (Order)» → `docs/modules/orders.md` · «Заявки», «Клієнт vs. Лід», «Список лідів» → `docs/modules/leads.md` · «Форма набору персоналу» → `docs/modules/recruitment.md` · «Binotel», «Мобільні оператори», «Офлайн-конверсії» → `docs/modules/integrations.md` · «Ролі та права», «Бокова панель ↔ права», «Дві панелі» → `docs/arch/access-roles-panels.md` · «Архітектура синхронізації», «Тимчасовий міст» → `docs/arch/sync-legacy.md` · «Стандартний фільтр дат» → `docs/ui/date-filter.md`.
