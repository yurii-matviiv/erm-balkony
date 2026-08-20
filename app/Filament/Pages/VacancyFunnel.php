<?php

namespace App\Filament\Pages;

use App\Models\Vacancy;
use App\Models\VacancyApplication;
use App\Models\VacancyFormView;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * "Воронка вакансій" — a quick read-only overview of how hiring is going:
 * how many times the public form was opened (and by how many unique
 * visitors), how many of those turned into applications, per vacancy and
 * per channel/UTM source, plus the same filters so the underlying list can
 * be narrowed down.
 *
 * "Views" (VacancyFormView) are logged on every GET of a public vacancy
 * form BEFORE the candidate fills anything in — see
 * App\Http\Controllers\Public\VacancyFormController. Applications entered
 * manually by staff have no matching views, which is expected: this page's
 * views/unique-visitors/conversion numbers are only meaningful for
 * self-service (ad) traffic, the application counts below them cover both.
 */
class VacancyFunnel extends Page implements HasTable
{
    use \App\Filament\Concerns\RequiresViewPermission;

    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static string|\UnitEnum|null $navigationGroup = 'Найм';

    protected static ?string $navigationLabel = 'Воронка вакансій';

    protected static ?string $title = 'Воронка вакансій';

    protected static ?string $slug = 'vacancy-funnel';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.vacancy-funnel';

    /**
     * Simple counts for the summary cards at the top of the page. Computed
     * fresh on every page load — the volumes here are small (hundreds, not
     * millions of applications/views), so there's no need for
     * caching/precomputed aggregates yet.
     *
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $total = VacancyApplication::count();
        $targeted = VacancyApplication::where('is_targeted', true)->count();

        $totalViews = VacancyFormView::count();
        $uniqueVisitors = VacancyFormView::query()->distinct('visitor_id')->count('visitor_id');

        $byVacancy = Vacancy::query()
            ->withCount(['applications', 'formViews'])
            ->orderByDesc('applications_count')
            ->get();

        $byChannel = VacancyApplication::query()
            ->selectRaw('advertising_channel, count(*) as count')
            ->groupBy('advertising_channel')
            ->orderByDesc('count')
            ->pluck('count', 'advertising_channel');

        // Which ad source actually drove opens of the form — this is the
        // number the user asked for: "аналітика по тому, як відвідують цю
        // форму, з якого джерела приходять".
        $viewsByUtmSource = VacancyFormView::query()
            ->selectRaw("COALESCE(NULLIF(utm_source, ''), 'Не вказано') as source, count(*) as count")
            ->groupBy('source')
            ->orderByDesc('count')
            ->pluck('count', 'source');

        return [
            'total' => $total,
            'targeted' => $targeted,
            'totalViews' => $totalViews,
            'uniqueVisitors' => $uniqueVisitors,
            'byVacancy' => $byVacancy,
            'byChannel' => $byChannel,
            'channelLabels' => VacancyApplication::channelOptions(),
            'viewsByUtmSource' => $viewsByUtmSource,
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(VacancyApplication::query())
            ->columns([
                TextColumn::make('candidate.full_name')
                    ->label('ПІБ')
                    ->description(fn (VacancyApplication $record): ?string => $record->candidate?->phone),

                TextColumn::make('vacancy.name')
                    ->label('Вакансія')
                    ->badge(),

                IconColumn::make('is_targeted')
                    ->label('Цільова')
                    ->boolean(),

                TextColumn::make('advertising_channel')
                    ->label('Канал')
                    ->formatStateUsing(fn (?string $state): string => VacancyApplication::channelOptions()[$state] ?? '—'),

                TextColumn::make('created_at')
                    ->label('Дата заявки')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('vacancy_id')
                    ->label('Вакансія')
                    ->relationship('vacancy', 'name'),

                SelectFilter::make('advertising_channel')
                    ->label('Канал')
                    ->options(VacancyApplication::channelOptions()),

                TernaryFilter::make('is_targeted')
                    ->label('Цільова заявка'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
