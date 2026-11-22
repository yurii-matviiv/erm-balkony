<?php

namespace App\Filament\Pages\Marketing;

use App\Filament\Concerns\HasInstructionModal;
use App\Filament\Concerns\RequiresViewPermission;
use App\Services\Seo\SeoReportService;
use App\Services\Seo\SeoSnapshotService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\DB;

/**
 * «SEO — позиції Google»: знімки з Search Console API (команда seo:positions-snapshot, щопонеділка).
 * Лише читання з БД; видима тільки super_admin, поки роль не ввімкнено в «Бокова панель».
 */
class SeoPositions extends Page
{
    use HasInstructionModal;
    use RequiresViewPermission;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-magnifying-glass-circle';

    protected static ?string $navigationLabel = 'SEO: позиції Google';

    protected static ?string $title = 'SEO: позиції Google';

    protected static ?string $slug = 'seo-positions';

    protected static string|\UnitEnum|null $navigationGroup = 'Маркетинг';

    protected static ?int $navigationSort = 30;

    protected Width|string|null $maxContentWidth = Width::Full;

    protected string $view = 'filament.pages.marketing.seo-positions';

    public string $activeTab = 'changes';

    /** Скільки останніх знімків показувати колонками. */
    private const SNAPSHOTS_SHOWN = 8;

    /** @return array<string,string> */
    public function getTabs(): array
    {
        return [
            'changes' => 'Що змінилось',
            'new_page' => 'Нові сторінки',
            'top_page' => 'Топ-30 сторінок',
            'top_query' => 'Топ-30 запитів',
            'index' => 'Індексація',
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('snapshotNow')
                ->label('Зробити знімок зараз')
                ->icon('heroicon-o-camera')
                ->requiresConfirmation()
                ->modalHeading('Зробити знімок позицій зараз?')
                ->modalDescription('Дані беруться з Google Search Console (лише читання) і записуються в базу. Повторний знімок того ж дня оновлює вже записаний.')
                ->action(function (): void {
                    try {
                        $svc = app(SeoSnapshotService::class);
                        $n = $svc->store($svc->collect());
                        Notification::make()->title("Знімок записано: {$n} рядків")->success()->send();
                    } catch (\Throwable $e) {
                        Notification::make()->title('Не вдалося зробити знімок: '.$e->getMessage())->danger()->send();
                    }
                }),
            Action::make('indexNow')
                ->label('Оновити індексацію')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Перевірити індексацію нових сторінок?')
                ->modalDescription('16 запитів до Google (URL Inspection), може тривати до хвилини.')
                ->action(function (): void {
                    try {
                        @set_time_limit(180);
                        $res = app(SeoSnapshotService::class)->storeIndexStatus(0, 100);
                        Notification::make()->title('Індексацію оновлено: '.count($res).' сторінок')->success()->send();
                    } catch (\Throwable $e) {
                        Notification::make()->title('Не вдалося оновити індексацію: '.$e->getMessage())->danger()->send();
                    }
                }),
        ];
    }

    public function seoInstructionAction(): Action
    {
        return $this->instructionAction('seoInstruction', 'SEO: позиції Google — як це працює', 'filament.pages.marketing.seo-positions-instruction', [
            'positionChange' => config('seo.position_change'),
            'impressionsPct' => config('seo.impressions_change_pct'),
            'minImpressions' => config('seo.min_impressions'),
            'windowDays' => config('seo.window_days'),
            'lagDays' => config('seo.lag_days'),
            'topLimit' => config('seo.top_limit'),
        ]);
    }

    protected function getViewData(): array
    {
        $dates = DB::table('seo_position_snapshots')->distinct()->orderByDesc('snapshot_date')
            ->limit(self::SNAPSHOTS_SHOWN)->pluck('snapshot_date')->sort()->values()->all();

        $data = ['dates' => $dates, 'matrix' => [], 'changes' => null, 'index' => collect(), 'queries' => collect()];

        if ($this->activeTab === 'changes') {
            $data['changes'] = app(SeoReportService::class)->changes();
        } elseif ($this->activeTab === 'index') {
            $last = DB::table('seo_index_status')->max('snapshot_date');
            $data['index'] = DB::table('seo_index_status')->where('snapshot_date', $last)->orderBy('page')->get();
        } elseif ($dates) {
            $scope = $this->activeTab;
            $rows = DB::table('seo_position_snapshots')->where('scope', $scope)->whereIn('snapshot_date', $dates)->get();
            $data['matrix'] = $rows->groupBy(fn ($r) => $scope === 'top_query' ? $r->query : $r->page)
                ->map(fn ($g) => $g->keyBy('snapshot_date'))
                ->sortBy(fn ($g) => -1 * ($g->last()->clicks * 100000 + $g->last()->impressions))
                ->all();

            if ($scope === 'new_page') {
                $last = end($dates);
                $data['queries'] = DB::table('seo_position_snapshots')->where('scope', 'new_page_query')
                    ->where('snapshot_date', $last)->orderByDesc('impressions')->get()->groupBy('page');
            }
        }

        return $data;
    }
}
