# Що прибрати після повного переходу на нову ERM

Усе, що існує лише заради старої CRM / старої бази, позначено в коді коментарем **`LEGACY-CLEANUP`**.
Пошук: `grep -rn "LEGACY-CLEANUP" app routes config docs resources`.

Два види позначок:
- **`LEGACY-CLEANUP (файл)`** на початку файлу: файл цілком потрібен лише для синхронізації; після переходу його видаляємо.
- **`LEGACY-CLEANUP:` в середині файлу**: у файлі лишається, але позначений фрагмент прибираємо / спрощуємо.

## Файли, що видаляються цілком (позначені)
- `app/Services/Sync/**` — усі мапери, реєстр, `LegacySupplierBridge`, контракти.
- `app/Console/Commands/SyncFromLegacy.php`, `SyncReset.php`.
- `app/Filament/Pages/SyncOverview.php`.

## Фрагменти, які прибираємо або спрощуємо
- `routes/console.php` — розклад `sync:legacy`.
- `ConversionPlanner::leadState()` — прибрати `legacy_status ??`, лишити `stage/status`; умову `legacy_status === 'reklamatsiya_amtech'` в
  правилі «Рекламація» (достатньо типу послуги).
- `AudienceClassify` — умова `legacy_status <> 'reklamatsiya_amtech'`.
- Колонки `legacy_id`, `legacy_status`, `legacy_status_new` у `leads`/`orders` та інших таблицях (окремою міграцією, коли переконаємось, що ніде не читаються).
- `LEGACY_DB_*` у `.env`, підключення до старої бази.
- Це **не** прибираємо: `process_steps.source = legacy` — історія, а не код синхронізації.

## Порядок переходу (чернетка)
1. Усі менеджери працюють у новій ERM.
2. Вимкнути розклад `sync:legacy` (рядок у `routes/console.php`). Перевірити `conversions:status`: нові події йдуть, як і раніше.
3. Деякий час тримати файли; потім видалити позначені файли, прибрати фрагменти, міграцією прибрати колонки `legacy_*`.
4. Під час кожного кроку звіряти `conversions:status` до і після (див. `docs/STAGES.md`, розділ «Google Ads: що не можна зламати»).
