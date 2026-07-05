<?php

namespace App\Filament\Pages;

use App\Models\AppSetting;
use App\Services\Sync\SyncMapperRegistry;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/**
 * "Синхронізація зі старої БД" — the entry point of the data migration tool.
 *
 * Header controls:
 *   • Toggle авто-синхронізації — вмикає/вимикає щохвилинний планувальник
 *     (php artisan sync:legacy --scheduled). Зберігається в app_settings.
 *   • "Синхронізувати все зараз" — запускає всі мапери in-process,
 *     аналог ручного `php artisan sync:legacy`.
 *
 * Table: one row per registered SyncMapper with old/new counts and status.
 * Clicking a row opens SyncTableDetail for side-by-side inspection.
 */
class SyncOverview extends Page implements HasTable
{
    use \App\Filament\Concerns\RequiresViewPermission;

    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-path';

    protected static string|\UnitEnum|null $navigationGroup = 'Синхронізація';

    protected static ?string $navigationLabel = 'Синхронізація зі старої БД';

    protected static ?string $title = 'Синхронізація зі старої БД';

    protected static ?string $slug = 'sync';

    protected string $view = 'filament.pages.sync-overview';

    // ──────────────────────────────────────────────
    // Header actions
    // ──────────────────────────────────────────────

    protected function getHeaderActions(): array
    {
        $autoEnabled = AppSetting::getBool('sync_auto_enabled', false);
        $lastRun     = AppSetting::get('sync_last_run_at');
        $lastRunText = $lastRun
            ? 'Останній синк: ' . \Carbon\Carbon::parse($lastRun)->diffForHumans()
            : 'Ще не запускався';

        return [
            // ── Toggle авто-синхронізації ──────────────────
            Action::make('toggle_auto_sync')
                ->label($autoEnabled ? 'Авто-синк: УВІМКНЕНО' : 'Авто-синк: ВИМКНЕНО')
                ->icon($autoEnabled ? 'heroicon-o-pause-circle' : 'heroicon-o-play-circle')
                ->color($autoEnabled ? 'success' : 'gray')
                ->badge($lastRunText)
                ->badgeColor('gray')
                ->action(function () use ($autoEnabled) {
                    $new = ! $autoEnabled;
                    AppSetting::set('sync_auto_enabled', $new ? '1' : '0');

                    Notification::make()
                        ->title($new
                            ? 'Авто-синхронізацію увімкнено'
                            : 'Авто-синхронізацію вимкнено')
                        ->body($new
                            ? 'Щохвилини буде запускатись sync:legacy --scheduled.'
                            : 'Планувальник більше не буде запускати синхронізацію.')
                        ->color($new ? 'success' : 'warning')
                        ->send();
                })
                ->requiresConfirmation(fn (): bool => $autoEnabled) // підтвердження тільки при вимкненні
                ->modalHeading('Вимкнути авто-синхронізацію?')
                ->modalDescription('Планувальник більше не буде синхронізувати дані зі старої БД. Вручну можна запустити в будь-який момент.')
                ->modalSubmitActionLabel('Вимкнути'),

            // ── Синхронізувати все зараз ───────────────────
            Action::make('sync_all_now')
                ->label('Синхронізувати все зараз')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Запустити повну синхронізацію?')
                ->modalDescription('Всі таблиці будуть синхронізовані зі старої БД. Це може зайняти кілька хвилин.')
                ->modalSubmitActionLabel('Запустити')
                ->action(function () {
                    $totalCreated = 0;
                    $totalUpdated = 0;
                    $totalSkipped = 0;
                    $failed       = 0;

                    foreach (SyncMapperRegistry::all() as $mapper) {
                        try {
                            $result        = $mapper->run();
                            $totalCreated += $result['created'];
                            $totalUpdated += $result['updated'];
                            $totalSkipped += $result['skipped'];
                        } catch (\Throwable) {
                            $failed++;
                        }
                    }

                    AppSetting::set('sync_last_run_at', now()->toIso8601String());

                    Notification::make()
                        ->title('Синхронізацію завершено')
                        ->body(implode(' · ', array_filter([
                            "Додано: {$totalCreated}",
                            "Оновлено: {$totalUpdated}",
                            $totalSkipped ? "Пропущено: {$totalSkipped}" : null,
                            $failed ? "Помилок: {$failed}" : null,
                        ])))
                        ->color($failed ? 'warning' : 'success')
                        ->send();
                }),
        ];
    }

    // ──────────────────────────────────────────────
    // Table
    // ──────────────────────────────────────────────

    public function table(Table $table): Table
    {
        return $table
            ->records(function (): array {
                $rows = [];

                foreach (SyncMapperRegistry::all() as $mapper) {
                    $oldCount    = $mapper->oldCount();
                    $syncedCount = $mapper->syncedCount();

                    $rows[$mapper->key()] = [
                        'key'          => $mapper->key(),
                        'label'        => $mapper->label(),
                        'old_table'    => $mapper->oldTable(),
                        'new_table'    => $mapper->newTable(),
                        'old_count'    => $oldCount,
                        'synced_count' => $syncedCount,
                        'status'       => match (true) {
                            $oldCount > 0 && $syncedCount >= $oldCount => 'done',
                            $syncedCount > 0                           => 'partial',
                            default                                    => 'none',
                        },
                    ];
                }

                return $rows;
            })
            ->columns([
                TextColumn::make('label')
                    ->label('Таблиця')
                    ->description(fn (array $record): string => "{$record['old_table']} → {$record['new_table']}")
                    ->weight('bold'),

                TextColumn::make('old_count')
                    ->label('Записів у старій БД')
                    ->numeric(),

                TextColumn::make('synced_count')
                    ->label('Синхронізовано в новій')
                    ->numeric(),

                IconColumn::make('status')
                    ->label('Статус')
                    ->icon(fn (string $state): string => match ($state) {
                        'done'    => 'heroicon-o-check-circle',
                        'partial' => 'heroicon-o-exclamation-circle',
                        default   => 'heroicon-o-minus-circle',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'done'    => 'success',
                        'partial' => 'warning',
                        default   => 'gray',
                    }),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('Переглянути')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn (array $record): string => SyncTableDetail::getUrl(['mapper' => $record['key']])),
            ]);
    }
}
