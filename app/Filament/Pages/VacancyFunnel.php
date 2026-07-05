<?php

namespace App\Filament\Pages;

use App\Models\Vacancy;
use App\Models\VacancyApplication;
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
 * how many applications per vacancy, per channel, and how many were
 * "цільові" (targeted) vs not, plus the same filters so the underlying
 * list can be narrowed down. Deliberately simple (per the initial request:
 * get the Vacancy entity itself in place and usable first, refine the
 * funnel visualization later once there is real data to look at).
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
     * millions of applications), so there's no need for caching/precomputed
     * aggregates yet.
     *
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $total = VacancyApplication::count();
        $targeted = VacancyApplication::where('is_targeted', true)->count();

        $byVacancy = Vacancy::query()
            ->withCount('applications')
            ->orderByDesc('applications_count')
            ->get();

        $byChannel = VacancyApplication::query()
            ->selectRaw('advertising_channel, count(*) as count')
            ->groupBy('advertising_channel')
            ->orderByDesc('count')
            ->pluck('count', 'advertising_channel');

        return [
            'total' => $total,
            'targeted' => $targeted,
            'byVacancy' => $byVacancy,
            'byChannel' => $byChannel,
            'channelLabels' => VacancyApplication::channelOptions(),
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
