<?php

namespace App\Filament\Pages\Marketing;

use App\Services\Leads\LeadExportQueryService;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * "Експорт лідів" — the read-only lead table for the external marketing
 * agency: view + filter by date + download as CSV. Ported from the
 * intermediate "erm-balkony--Only-for-marketing-page" project; data now
 * comes from OUR database via LeadExportQueryService (see its docblock).
 *
 * Access: Shield permission `View:LeadExport` only (no hasRole() — project
 * convention). The `marketing_agency` role created by migration
 * 2026_07_03_120000 carries exactly this one permission, so an agency
 * account sees this page and nothing else. shouldRegisterNavigation() is
 * deliberately NOT overridden — returning false there would hide the page
 * from NavigationCatalog::discover() and make it unmanageable from the
 * "Бокова панель" settings page.
 */
class LeadExport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-arrow-down';

    protected static string|\UnitEnum|null $navigationGroup = 'Маркетинг';

    protected static ?string $navigationLabel = 'Експорт лідів';

    protected static ?string $title = 'Експорт лідів';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.marketing.lead-export';

    protected Width|string|null $maxContentWidth = Width::Full;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('View:LeadExport') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table

            ->query(
                app(LeadExportQueryService::class)->getQuery()
            )

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
                            ]),

                        DatePicker::make('date_from')
                            ->label('Дата від')
                            // Only meaningful for the "custom" preset —
                            // hidden otherwise so the two date inputs
                            // don't LOOK active while a preset silently
                            // wins (that mismatch confused users of the
                            // old project's always-visible variant).
                            ->visible(fn (callable $get): bool => $get('preset') === 'custom'),

                        DatePicker::make('date_to')
                            ->label('Дата до')
                            ->visible(fn (callable $get): bool => $get('preset') === 'custom'),

                    ])

                    ->query(function (Builder $query, array $data): Builder {
                        return app(LeadExportQueryService::class)
                            ->applyDateFilters($query, $data);
                    })

                    ->indicateUsing(function (array $data): ?string {
                        $preset = $data['preset'] ?? null;

                        if ($preset === 'custom') {
                            return trim(sprintf(
                                'Період: %s — %s',
                                $data['date_from'] ? Carbon::parse($data['date_from'])->format('d.m.Y') : '…',
                                $data['date_to'] ? Carbon::parse($data['date_to'])->format('d.m.Y') : '…',
                            ));
                        }

                        return match ($preset) {
                            'today' => 'Період: сьогодні',
                            'yesterday' => 'Період: вчора',
                            'this_month' => 'Період: поточний місяць',
                            'last_30_days' => 'Період: останні 30 днів',
                            'this_year' => 'Період: поточний рік',
                            default => null,
                        };
                    }),

            ])

            ->deferLoading()

            ->defaultSort('leads.id', 'desc')

            ->paginated([25, 50, 100])

            ->striped()

            ->columns([

                // Qualified sort columns everywhere below — `id` and
                // `created_at` exist on several of the joined tables, and
                // an unqualified ORDER BY would be ambiguous SQL.
                TextColumn::make('id')
                    ->label('№')
                    ->sortable(['leads.id']),

                TextColumn::make('created_at')
                    ->label('час')
                    ->dateTime('d.m.Y H:i:s')
                    ->sortable(['leads.created_at']),

                TextColumn::make('utm_source')
                    ->label('джерело трафіку'),

                TextColumn::make('source')
                    ->label('подія')
                    ->formatStateUsing(
                        fn (?string $state): string => LeadExportQueryService::sourceLabel($state)
                    )
                    ->badge(),

                // Derived, not a real column — state() instead of a column
                // name, same trick as the old project's BadgeColumn.
                TextColumn::make('lead_target_status')
                    ->label('цільовий')
                    ->state(fn ($record): string => LeadExportQueryService::targetLabel($record->stage, $record->lead_status))
                    ->badge()
                    ->color(fn ($record): string => LeadExportQueryService::targetColor($record->stage, $record->lead_status)),

                TextColumn::make('lead_status_group')
                    ->label('статус')
                    ->state(fn ($record): string => LeadExportQueryService::statusGroupLabel($record->stage, $record->lead_status))
                    ->badge()
                    ->color(fn ($record): string => LeadExportQueryService::statusGroupColor($record->stage, $record->lead_status)),

                TextColumn::make('client_name')
                    ->label('імʼя')
                    // client_name is a computed select alias, so the
                    // default whereColumn search would fail — search the
                    // underlying structured columns instead.
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $query) use ($search): void {
                            $query->where('clients.first_name', 'like', "%{$search}%")
                                ->orWhere('clients.last_name', 'like', "%{$search}%")
                                ->orWhere('clients.phone', 'like', "%{$search}%");
                        });
                    }),

                TextColumn::make('email')
                    ->label('email')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('phone')
                    ->label('телефон'),

                TextColumn::make('total_price')
                    ->label('сума')
                    ->money('UAH', divideBy: 1),

                TextColumn::make('utm_campaign')
                    ->label('кампанія')
                    ->wrap()
                    ->limit(40),

                TextColumn::make('gclid')
                    ->label('gclid')
                    ->state(fn ($record): string => filled($record->gclid) ? 'отримано' : 'не отримано')
                    ->badge()
                    ->color(fn ($record): string => filled($record->gclid) ? 'success' : 'gray')
                    ->toggleable(isToggledHiddenByDefault: true),

            ]);
    }

    protected function getHeaderActions(): array
    {
        return [

            Action::make('exportCsv')
                ->label('Скачати CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('warning')
                // The CSV must contain exactly what the table shows, so
                // the current date filter is forwarded to the download
                // route as query params — LeadExportController applies it
                // through the SAME LeadExportQueryService::applyDateFilters().
                ->url(function (): string {
                    $filters = $this->tableFilters['date_range'] ?? [];

                    return route('lead-export.page', [
                        'preset' => $filters['preset'] ?? null,
                        'date_from' => $filters['date_from'] ?? null,
                        'date_to' => $filters['date_to'] ?? null,
                    ]);
                })
                ->openUrlInNewTab(),

        ];
    }
}
