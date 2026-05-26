<?php

namespace App\Filament\Pages\Exports;

use App\Services\Leads\LeadQueryService;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;

use Filament\Actions\Action; 

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class LeadExport extends Page implements HasTable
{
    use InteractsWithTable;

    protected \Filament\Support\Enums\Width|string|null $maxContentWidth = \Filament\Support\Enums\Width::Full;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-arrow-down';

    protected static ?string $navigationLabel = 'Lead Export';

    protected static ?string $title = 'Lead Export';

    protected static string|\UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.exports.lead-export';

    /**
     * ---------------------------------------------------------
     * ACCESS
     * ---------------------------------------------------------
     */
    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    /**
     * ---------------------------------------------------------
     * TABLE
     * ---------------------------------------------------------
     */
    public function table(Table $table): Table
    {
        return $table

        ->filters([

    Filter::make('date_range')

        ->form([

            Select::make('preset')
    ->label('Період')
    ->default('this_year')
    ->live()

    ->options([

        'today' => 'Сьогодні',
        'yesterday' => 'Вчора',
        'this_month' => 'Поточний місяць',
        'last_30_days' => 'Останні 30 днів',
        'this_year' => 'Поточний рік',
        'custom' => 'Свій варіант',

    ])

    ->afterStateUpdated(function ($state, callable $set) {

        match ($state) {

            'today' => [
                $set('date_from', now()->startOfDay()),
                $set('date_to', now()->endOfDay()),
            ],

            'yesterday' => [
                $set('date_from', now()->subDay()->startOfDay()),
                $set('date_to', now()->subDay()->endOfDay()),
            ],

            'this_month' => [
                $set('date_from', now()->startOfMonth()),
                $set('date_to', now()->endOfMonth()),
            ],

            'last_30_days' => [
                $set('date_from', now()->subDays(30)),
                $set('date_to', now()),
            ],

            'this_year' => [
                $set('date_from', now()->startOfYear()),
                $set('date_to', now()->endOfYear()),
            ],

            default => null,
        };
    }),

            DatePicker::make('date_from')
                ->label('Дата від')
                ->default(now()->startOfYear()),

            DatePicker::make('date_to')
                ->label('Дата до')
                ->default(now()),

        ])

        ->query(function (Builder $query, array $data): Builder {

            /**
             * ---------------------------------------------------------
             * PRESET
             * ---------------------------------------------------------
             */
            $preset = $data['preset'] ?? 'this_year';

            return match ($preset) {

                'today' => $query->whereDate(
                    'leads.created_at',
                    today()
                ),

                'yesterday' => $query->whereDate(
                    'leads.created_at',
                    today()->subDay()
                ),

                'this_month' => $query
                    ->whereDate('leads.created_at', '>=', now()->startOfMonth())
                    ->whereDate('leads.created_at', '<=', now()),

                'last_30_days' => $query
                    ->whereDate('leads.created_at', '>=', now()->subDays(30))
                    ->whereDate('leads.created_at', '<=', now()),

                'custom' => $query
                    ->when(
                        $data['date_from'],
                        fn (Builder $query, $date): Builder => $query->whereDate(
                            'leads.created_at',
                            '>=',
                            Carbon::parse($date)
                        )
                    )
                    ->when(
                        $data['date_to'],
                        fn (Builder $query, $date): Builder => $query->whereDate(
                            'leads.created_at',
                            '<=',
                            Carbon::parse($date)
                        )
                    ),

                default => $query
                    ->whereDate('leads.created_at', '>=', now()->startOfYear())
                    ->whereDate('leads.created_at', '<=', now()),
            };
        }),
])

            ->deferLoading()

            ->query(
                app(LeadQueryService::class)->getQuery()
            )

            ->defaultSort('leads.id', 'desc')

            ->paginated([25, 50, 100])

            ->striped()

            ->columns([

                /**
                 * ---------------------------------------------------------
                 * №
                 * ---------------------------------------------------------
                 */
                Tables\Columns\TextColumn::make('id')
                    ->label('№')
                    ->sortable(),

                /**
                 * ---------------------------------------------------------
                 * ЧАС
                 * ---------------------------------------------------------
                 */
                Tables\Columns\TextColumn::make('created_at')
                    ->label('час')
                    ->dateTime('d.m.Y H:i:s')
                    ->sortable(),

                /**
                 * ---------------------------------------------------------
                 * ДЖЕРЕЛО ТРАФІКУ
                 * ---------------------------------------------------------
                 */
                Tables\Columns\TextColumn::make('utm_source')
                    ->label('джерело трафіку'),

                /**
                 * ---------------------------------------------------------
                 * ПОДІЯ
                 * ---------------------------------------------------------
                 */
                Tables\Columns\TextColumn::make('source')
                    ->label('подія')
                    ->formatStateUsing(function (?string $state): string {

                        return match ($state) {

                            'call' => 'Дзвінок',
                            'office-visit' => 'Візит в офіс',
                            'binotel_chat' => 'Binotel chat',
                            'site' => 'Заявка з сайту',
                            'get_call_binotel' => 'Зворотній дзвінок',
                            'fb_lid' => 'Facebook lead',
                            'fb_chat' => 'Facebook chat',

                            default => '-',
                        };
                    })
                    ->badge(),

                /**
                 * ---------------------------------------------------------
                 * ЦІЛЬОВИЙ
                 * ---------------------------------------------------------
                 */
                Tables\Columns\BadgeColumn::make('lead_target_status')
                    ->label('цільовий')

                    ->state(function ($record) {

                        return $record->lead_status;
                    })

                    ->formatStateUsing(function (?string $state): string {

                        return match ($state) {

                            'processing',
                            'zamir',
                            'vizyt_ofis',
                            'accepted',
                            'measuring' => 'цільовий',

                            'not_targeted',
                            'another_city',
                            'reklamatsiya_amtech',
                            'reklamatsiya' => 'не цільовий',

                            'new' => 'невідомо',

                            default => 'інше',
                        };
                    })

                    ->color(function (?string $state): string {

                        return match ($state) {

                            'processing',
                            'zamir',
                            'vizyt_ofis',
                            'accepted',
                            'measuring' => 'success',

                            'not_targeted',
                            'another_city',
                            'reklamatsiya_amtech',
                            'reklamatsiya' => 'danger',

                            'new' => 'gray',

                            default => 'warning',
                        };
                    }),

                /**
                 * ---------------------------------------------------------
                 * СТАТУС
                 * ---------------------------------------------------------
                 */
                Tables\Columns\BadgeColumn::make('lead_status_group')
                    ->label('статус')

                    ->state(function ($record) {

                        return $record->lead_status;
                    })

                    ->formatStateUsing(function (?string $state): string {

                        return match ($state) {

                            'new' => 'новий',

                            'processing',
                            'zamir',
                            'vizyt_ofis',
                            'measuring' => 'в роботі',

                            'accepted' => 'продано',

                            'canceled',
                            'not_targeted',
                            'another_city',
                            'propushcheno',
                            'reklamatsiya',
                            'reklamatsiya_amtech' => 'скасовано',

                            default => 'невідомо',
                        };
                    })

                    ->color(function (?string $state): string {

                        return match ($state) {

                            'new' => 'gray',

                            'processing',
                            'zamir',
                            'vizyt_ofis',
                            'measuring' => 'warning',

                            'accepted' => 'success',

                            'canceled',
                            'not_targeted',
                            'another_city',
                            'propushcheno',
                            'reklamatsiya',
                            'reklamatsiya_amtech' => 'danger',

                            default => 'gray',
                        };
                    }),

                /**
                 * ---------------------------------------------------------
                 * ІМʼЯ
                 * ---------------------------------------------------------
                 */
                Tables\Columns\TextColumn::make('name')
                    ->label('імʼя')
                    ->searchable(),

                /**
                 * ---------------------------------------------------------
                 * EMAIL
                 * ---------------------------------------------------------
                 */
                Tables\Columns\TextColumn::make('email')
                    ->label('email')
                    ->toggleable(isToggledHiddenByDefault: true),
                    

                /**
                 * ---------------------------------------------------------
                 * ТЕЛЕФОН
                 * ---------------------------------------------------------
                 */
                Tables\Columns\TextColumn::make('phone')
                    ->label('телефон'),

                /**
                 * ---------------------------------------------------------
                 * СУМА
                 * ---------------------------------------------------------
                 */
                Tables\Columns\TextColumn::make('total_price')
                    ->label('сума')
                    ->money('UAH', divideBy: 1),

                /**
                 * ---------------------------------------------------------
                 * КАМПАНІЯ
                 * ---------------------------------------------------------
                 */
                Tables\Columns\TextColumn::make('utm_campaign')
                    ->label('кампанія')
                    ->wrap()
                    ->limit(40),

                /**
                 * ---------------------------------------------------------
                 * GCLID
                 * ---------------------------------------------------------
                 */
              Tables\Columns\TextColumn::make('gclid')

    ->label('gclid')

    ->state(function ($record): string {

        return filled($record->gclid)
            ? 'отримано'
            : 'не отримано';
    })

    ->badge()

    ->color(function ($record): string {

        return filled($record->gclid)
            ? 'success'
            : 'gray';
    })

    ->toggleable(isToggledHiddenByDefault: true),

                    
            ]);

            
    }

        protected function getHeaderActions(): array
    {
        return [

            Action::make('exportCsv')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
               ->url(fn (): string => route('lead-export.csv'))

        ];
    }

    public function mount(): void
{
    /**
     * ---------------------------------------------------------
     * LIMIT FOR NON ADMIN
     * ---------------------------------------------------------
     */
    if (! auth()->user()?->hasRole('admin')) {

        abort_unless(
            now()->year <= now()->year,
            403
        );
    }
}

 
}
