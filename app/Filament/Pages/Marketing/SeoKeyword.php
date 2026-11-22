<?php

namespace App\Filament\Pages\Marketing;

use App\Filament\Concerns\PersistsPageState;
use App\Filament\Concerns\RequiresViewPermission;
use App\Filament\Widgets\SeoKeywordStats;
use App\Services\Seo\SeoKeywordService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * «Ключове слово»: окрема сторінка аналізу одного пошукового запиту — з яких сторінок він приходить,
 * динаміка по днях і зі знімків, пристрої/країни, схожі запити, заявки зі сторінок. Лише стандартні елементи Filament.
 * Відкривається зі списку слів на «SEO: позиції Google» або кнопкою «Аналіз слова».
 */
class SeoKeyword extends Page implements HasTable
{
    use InteractsWithTable;
    use PersistsPageState;
    use RequiresViewPermission;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-key';

    protected static ?string $slug = 'seo-keyword';

    // Не окремий пункт меню: сторінка відкривається зі списку слів.
    protected static bool $shouldRegisterNavigation = false;

    protected Width|string|null $maxContentWidth = Width::Full;

    protected string $view = 'filament.pages.marketing.seo-keyword';

    #[Url(as: 'q')]
    public string $keyword = '';

    public string $activeTab = 'pages';

    public function mount(): void
    {
        $this->restorePageState();
    }

    /** Остання вкладка слова (саме слово — в адресі ?q=). */
    protected function persistedState(): array
    {
        return ['activeTab' => array_keys($this->getTabs())];
    }

    public function getTitle(): string
    {
        return 'Ключове слово: «'.$this->keyword.'»';
    }

    /** @return array<string,string> */
    public function getTabs(): array
    {
        return [
            'pages' => 'Сторінки',
            'days' => 'По днях',
            'history' => 'Історія знімків',
            'segments' => 'Пристрої й країни',
            'related' => 'Схожі запити',
            'crm' => 'Заявки зі сторінок',
        ];
    }

    public function setTab(string $tab): void
    {
        if (array_key_exists($tab, $this->getTabs())) {
            $this->activeTab = $tab;
            $this->resetPage();
        }
    }

    protected function getHeaderWidgets(): array
    {
        return [SeoKeywordStats::make(['keyword' => $this->keyword])];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')->label('До списку')->icon('heroicon-o-arrow-left')->color('gray')
                ->url(fn (): string => SeoPositions::getUrl()),
            Action::make('google')->label('Відкрити видачу в Google')->icon('heroicon-o-arrow-top-right-on-square')->color('gray')
                ->url(fn (): string => 'https://www.google.com/search?q='.urlencode($this->keyword), shouldOpenInNewTab: true),
            Action::make('other')->label('Інше слово')->icon('heroicon-o-magnifying-glass')
                ->modalHeading('Аналіз ключового слова')
                ->modalSubmitActionLabel('Відкрити')
                ->schema([TextInput::make('keyword')->label('Слово або фраза (точно, як у Google)')->required()])
                ->action(fn (array $data) => $this->redirect(static::getUrl(['q' => trim($data['keyword'])]))),
        ];
    }

    public function table(Table $table): Table
    {
        $on = fn (string ...$tabs): \Closure => fn (): bool => in_array($this->activeTab, $tabs, true);
        $pos = fn ($state): string => $state === null ? '—' : number_format((float) $state, 1, ',', '');
        $metric = $on('pages', 'days', 'history', 'segments', 'related');

        return $table
            ->records(fn (?string $sortColumn, ?string $sortDirection): Collection => $this->records($sortColumn, $sortDirection))
            ->striped()
            ->persistSortInSession() // стандартне запам'ятовування сортування Filament (сесія)
            ->paginated(false)
            ->columns([
                TextColumn::make('label')
                    ->label(fn (): string => match ($this->activeTab) {
                        'pages', 'crm' => 'Сторінка', 'days', 'history' => 'Дата', 'related' => 'Запит', default => 'Розріз',
                    })
                    ->wrap()
                    ->description(fn (array $record): ?string => $record['sub'] ?? null)
                    ->url(fn (array $record): ?string => isset($record['keyword']) ? static::getUrl(['q' => $record['keyword']]) : ($record['url'] ?? null))
                    ->openUrlInNewTab(fn (): bool => in_array($this->activeTab, ['pages', 'crm'], true)),
                TextColumn::make('impressions')->label('Покази')->numeric()->sortable()->visible($metric),
                TextColumn::make('clicks')->label('Кліки')->numeric()->sortable()->visible($metric),
                TextColumn::make('ctr')->label('CTR')->formatStateUsing(fn ($s): string => number_format((float) $s * 100, 1, ',', '').'%')->sortable()->visible($metric),
                TextColumn::make('position')->label('Позиція')->formatStateUsing($pos)->sortable()->visible($metric),
                TextColumn::make('delta')->label('Зміна позиції')->visible($on('history'))
                    ->formatStateUsing(fn ($s): string => $s === null ? '—' : ($s < 0 ? '▲ ' : '▼ +').number_format(abs((float) $s), 1, ',', ''))
                    ->color(fn ($s): ?string => $s === null || abs((float) $s) < 0.05 ? null : ($s < 0 ? 'success' : 'danger')),
                TextColumn::make('leads')->label('Заявок')->numeric()->sortable()->visible($on('crm')),
                TextColumn::make('orders')->label('Замовлень')->numeric()->sortable()->visible($on('crm')),
                TextColumn::make('turnover')->label('Оборот, грн')->numeric()->sortable()->visible($on('crm')),
            ])
            ->emptyStateHeading('Даних немає')
            ->emptyStateDescription(fn (): string => $this->activeTab === 'history'
                ? 'Це слово ще не потрапляло у знімки (воно не в топ-30). Дані Google дивіться на інших вкладках.'
                : 'Google не показав це слово за період або запит не вдався.');
    }

    private function records(?string $sortColumn, ?string $sortDirection): Collection
    {
        $svc = app(SeoKeywordService::class);

        if ($this->activeTab === 'history') {
            $rows = collect($svc->history($this->keyword));
        } else {
            $live = $svc->live($this->keyword);
            if (! empty($live['error'])) {
                Notification::make()->title('Google не відповів: '.$live['error'])->danger()->send();
            }
            $rows = match ($this->activeTab) {
                'pages' => collect($live['pages']),
                'days' => collect($live['days'])->reverse(),
                'segments' => collect($live['devices'])->merge($live['countries']),
                'related' => collect($live['related']),
                'crm' => collect($svc->crm(array_column($live['pages'], 'url') ?: $this->storedPages())),
                default => collect(),
            };
        }

        if ($sortColumn) {
            $rows = $rows->sortBy(fn (array $r) => $r[$sortColumn] ?? 0, SORT_REGULAR, $sortDirection === 'desc');
        }

        return $rows->values();
    }

    /** Сторінки слова зі знімків (якщо Google для нього нічого не повернув). */
    private function storedPages(): array
    {
        return \Illuminate\Support\Facades\DB::table('seo_position_snapshots')->where('scope', 'query_page')
            ->where('query', $this->keyword)->distinct()->pluck('page')->all();
    }
}
