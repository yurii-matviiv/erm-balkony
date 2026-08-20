<?php

namespace App\Services\Marketing;

use App\Models\Vacancy;
use App\Models\VacancyApplication;
use App\Models\VacancyFormView;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Query logic behind "Аналітика форми набору" — kept out of the Page class,
 * same split as `FinanceAnalyticsService` on the finance side. Reads
 * `vacancy_form_views` (every page open, before the candidate fills
 * anything in) and `vacancy_applications` + `vacancy_application_marketing_data`
 * (actual submissions and their UTM snapshot).
 *
 * Every method takes a neutral `['from' => Y-m-d, 'to' => Y-m-d]` range (see
 * `App\Services\Finance\FinanceDateRange::parseDateRange()`, reused as-is —
 * it's plain date-string logic with nothing finance-specific in it, so a
 * second copy would just be duplication) and an optional vacancy id filter.
 */
class VacancyFormAnalyticsService
{
    /** How many most-recent days getDailyBreakdown() will return at most. */
    private const MAX_DAILY_ROWS = 90;

    /**
     * @param  array{from: string, to: string}  $range  Y-m-d dates (inclusive)
     * @return array{views: int, uniqueVisitors: int, applications: int, conversion: ?float}
     */
    public function getSummary(array $range, ?int $vacancyId): array
    {
        [$from, $to] = [$range['from'].' 00:00:00', $range['to'].' 23:59:59'];

        $viewsQuery = fn () => VacancyFormView::query()
            ->when($vacancyId, fn ($q) => $q->where('vacancy_id', $vacancyId))
            ->whereBetween('created_at', [$from, $to]);

        $views = $viewsQuery()->count();
        // Separate query (not a mutated clone) for the DISTINCT count — the
        // `->distinct()` flag sticks to the builder, so reusing the same
        // instance for both counts would silently distinct-ify the total too.
        $uniqueVisitors = $viewsQuery()->distinct('visitor_id')->count('visitor_id');

        $applications = VacancyApplication::query()
            ->when($vacancyId, fn ($q) => $q->where('vacancy_id', $vacancyId))
            ->whereBetween('created_at', [$from, $to])
            ->count();

        return [
            'views' => $views,
            'uniqueVisitors' => $uniqueVisitors,
            'applications' => $applications,
            'conversion' => $uniqueVisitors > 0 ? round($applications / $uniqueVisitors * 100, 1) : null,
        ];
    }

    /**
     * Always "today" (server date), regardless of the page's date filter —
     * same reasoning as the live mobile-operator balances on the finance
     * analytics page: one number that stays useful without touching the
     * filter, for "статистика за сьогодні" while running an active campaign.
     *
     * @return array{views: int, uniqueVisitors: int, applications: int, conversion: ?float}
     */
    public function getTodaySummary(?int $vacancyId): array
    {
        $today = now()->toDateString();

        return $this->getSummary(['from' => $today, 'to' => $today], $vacancyId);
    }

    /**
     * One row per UTM source that appeared either as a view or as an
     * application in the period — answers "звідки приходить заявка"
     * directly by combining traffic (views/unique) and outcome
     * (applications) for the same source in one table, sorted by traffic.
     *
     * @param  array{from: string, to: string}  $range
     * @return Collection<int, array{source: string, views: int, uniqueVisitors: int, applications: int, conversion: ?float}>
     */
    public function getSourceBreakdown(array $range, ?int $vacancyId): Collection
    {
        [$from, $to] = [$range['from'].' 00:00:00', $range['to'].' 23:59:59'];

        $viewsBySource = VacancyFormView::query()
            ->when($vacancyId, fn ($q) => $q->where('vacancy_id', $vacancyId))
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw("COALESCE(NULLIF(utm_source, ''), 'Не вказано') as source, count(*) as views, count(distinct visitor_id) as unique_visitors")
            ->groupBy('source')
            ->get()
            ->keyBy('source');

        // Joined through the marketing-data snapshot (not vacancy_applications
        // directly) because that's where a submission's own utm_source lives
        // — see VacancyApplicationMarketingData.
        $applicationsBySource = VacancyApplication::query()
            ->join('vacancy_application_marketing_data', 'vacancy_application_marketing_data.vacancy_application_id', '=', 'vacancy_applications.id')
            ->when($vacancyId, fn ($q) => $q->where('vacancy_applications.vacancy_id', $vacancyId))
            ->whereBetween('vacancy_applications.created_at', [$from, $to])
            ->selectRaw("COALESCE(NULLIF(vacancy_application_marketing_data.utm_source, ''), 'Не вказано') as source, count(*) as applications")
            ->groupBy('source')
            ->pluck('applications', 'source');

        $sources = $viewsBySource->keys()->merge($applicationsBySource->keys())->unique()->values();

        return $sources
            ->map(function (string $source) use ($viewsBySource, $applicationsBySource): array {
                $views = (int) ($viewsBySource[$source]->views ?? 0);
                $unique = (int) ($viewsBySource[$source]->unique_visitors ?? 0);
                $applications = (int) ($applicationsBySource[$source] ?? 0);

                return [
                    'source' => $source,
                    'views' => $views,
                    'uniqueVisitors' => $unique,
                    'applications' => $applications,
                    'conversion' => $unique > 0 ? round($applications / $unique * 100, 1) : null,
                ];
            })
            ->sortByDesc('views')
            ->values();
    }

    /**
     * One row per calendar day in the range, newest first, gaps filled with
     * zeros so the table reads as a clean trend instead of skipping quiet
     * days. Capped to the most recent MAX_DAILY_ROWS days — a wide preset
     * like "Поточний рік" would otherwise render a 300+ row table; the
     * summary cards above still cover the FULL selected range regardless.
     *
     * @param  array{from: string, to: string}  $range
     * @return array{days: Collection<int, array{date: string, views: int, uniqueVisitors: int, applications: int}>, truncated: bool}
     */
    public function getDailyBreakdown(array $range, ?int $vacancyId): array
    {
        $rangeStart = Carbon::parse($range['from']);
        $rangeEnd = Carbon::parse($range['to']);

        $cappedStart = $rangeEnd->copy()->subDays(self::MAX_DAILY_ROWS - 1);
        $truncated = $cappedStart->gt($rangeStart);
        $from = $truncated ? $cappedStart : $rangeStart;

        [$fromSql, $toSql] = [$from->toDateString().' 00:00:00', $rangeEnd->toDateString().' 23:59:59'];

        $viewsByDay = VacancyFormView::query()
            ->when($vacancyId, fn ($q) => $q->where('vacancy_id', $vacancyId))
            ->whereBetween('created_at', [$fromSql, $toSql])
            ->selectRaw('DATE(created_at) as day, count(*) as views, count(distinct visitor_id) as unique_visitors')
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        $applicationsByDay = VacancyApplication::query()
            ->when($vacancyId, fn ($q) => $q->where('vacancy_id', $vacancyId))
            ->whereBetween('created_at', [$fromSql, $toSql])
            ->selectRaw('DATE(created_at) as day, count(*) as applications')
            ->groupBy('day')
            ->pluck('applications', 'day');

        $days = collect();
        $cursor = $from->copy();

        while ($cursor->lte($rangeEnd)) {
            $key = $cursor->toDateString();

            $days->push([
                'date' => $key,
                'views' => (int) ($viewsByDay[$key]->views ?? 0),
                'uniqueVisitors' => (int) ($viewsByDay[$key]->unique_visitors ?? 0),
                'applications' => (int) ($applicationsByDay[$key] ?? 0),
            ]);

            $cursor->addDay();
        }

        return [
            'days' => $days->reverse()->values(),
            'truncated' => $truncated,
        ];
    }

    /** @return array<int, string> vacancy id => name, for the filter dropdown. */
    public function vacancyOptions(): array
    {
        return Vacancy::query()->orderBy('name')->pluck('name', 'id')->all();
    }
}
