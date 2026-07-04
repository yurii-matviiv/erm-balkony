<?php

namespace App\Filament\Pages;

use App\Services\Sync\Contracts\SyncMapper;
use App\Services\Sync\SyncMapperRegistry;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Detail/comparison page for ONE sync mapper (e.g. "users").
 *
 * Shows, side by side:
 * - the field mapping (which old column becomes which new column, and how)
 * - a page of raw rows from the OLD table (left)
 * - a page of the matching, already-synced rows in the NEW table (right)
 *
 * Reached only via SyncOverview (the "Переглянути" row action) — it is
 * intentionally NOT in the sidebar navigation, since it only makes sense
 * in the context of a specific table.
 *
 * The mapper to display is chosen via the `mapper` query string parameter,
 * e.g. /admin/sync-table?mapper=users.
 *
 * IMPORTANT: tools like `shield:generate` instantiate every page class and
 * call methods like getTitle() OUTSIDE of a normal page request — i.e.
 * WITHOUT ever calling mount() and WITHOUT a `mapper` query parameter.
 * Every method here must therefore tolerate getMapper() returning null,
 * instead of assuming mount() already validated it.
 */
class SyncTableDetail extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-path';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'sync-table';

    protected string $view = 'filament.pages.sync-table-detail';

    public string $mapperKey = '';

    /**
     * How many rows to show per page, on each side.
     */
    protected int $perPage = 15;

    /**
     * Only enforced during a real page visit (mount() runs). Tools that
     * inspect the page class without visiting it (see class docblock)
     * never call mount(), so they never hit this check — which is exactly
     * why every other method below must cope with a null mapper too.
     */
    public function mount(): void
    {
        $this->mapperKey = (string) request()->query('mapper', '');

        abort_unless(SyncMapperRegistry::find($this->mapperKey), 404,
            'Невідома таблиця синхронізації: '.$this->mapperKey);
    }

    public function getTitle(): string
    {
        $mapper = $this->getMapper();

        return $mapper ? 'Синхронізація: '.$mapper->label() : 'Синхронізація';
    }

    protected function getMapper(): ?SyncMapper
    {
        return SyncMapperRegistry::find($this->mapperKey);
    }

    /**
     * One page of raw rows from the OLD (legacy) table.
     */
    public function getOldRecords(): ?LengthAwarePaginator
    {
        $page = (int) request()->query('old_page', 1);

        return $this->getMapper()?->oldRecords($page, $this->perPage);
    }

    /**
     * One page of already-synced rows from the NEW table.
     */
    public function getNewRecords(): ?LengthAwarePaginator
    {
        $page = (int) request()->query('new_page', 1);

        return $this->getMapper()?->newRecords($page, $this->perPage);
    }

    public function getFieldMap(): array
    {
        return $this->getMapper()?->fieldMap() ?? [];
    }

    protected function getHeaderActions(): array
    {
        $mapper = $this->getMapper();

        return [
            Action::make('back')
                ->label('До списку таблиць')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(SyncOverview::getUrl()),

            Action::make('sync')
                ->label('Синхронізувати')
                ->icon('heroicon-o-arrow-path')
                ->visible($mapper !== null)
                ->requiresConfirmation()
                ->modalHeading('Синхронізувати «'.($mapper?->label() ?? '').'»?')
                ->modalDescription('Буде прочитано всі записи зі старої таблиці «'.($mapper?->oldTable() ?? '').'» і додано/оновлено відповідні записи в новій таблиці «'.($mapper?->newTable() ?? '').'». Стара база даних залишається незмінною — дані тільки читаються з неї.')
                ->action(function (): void {
                    $stats = $this->getMapper()?->run();

                    if ($stats === null) {
                        return;
                    }

                    Notification::make()
                        ->title('Синхронізацію завершено')
                        ->body("Додано: {$stats['created']} · Оновлено: {$stats['updated']} · Пропущено (помилки): {$stats['skipped']}")
                        ->success()
                        ->send();
                }),
        ];
    }
}
