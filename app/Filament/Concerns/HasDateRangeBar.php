<?php

namespace App\Filament\Concerns;

use App\Services\Finance\FinanceDateRange;
use Illuminate\Database\Eloquent\Builder;

/**
 * Standard page-level date filter (same behaviour as "Платежі" /
 * "Аналітика рахунків"): ALWAYS visible above the table, never inside the
 * table's filter panel. Pick a preset and both date inputs show the concrete
 * range; edit a date by hand and the preset flips to "Довільний період".
 *
 * Use: `use HasDateRangeBar;`, render `@include('filament.components.date-bar')`
 * above the table, and call applyDateRange($query, 'table.column') in the
 * page's query. Livewire calls mountHasDateRangeBar() on mount automatically.
 */
trait HasDateRangeBar
{
    public string $preset = 'today';

    public string $dateFrom = '';

    public string $dateTo = '';

    public function mountHasDateRangeBar(): void
    {
        // A period the user picked is remembered until the end of the
        // current day (per page, in the session); the next day the bar
        // starts again from "Сьогодні".
        $saved = session($this->dateRangeSessionKey());

        if (is_array($saved) && ($saved['day'] ?? null) === now()->toDateString()) {
            $this->preset = (string) $saved['preset'];

            if ($this->preset === 'custom') {
                $this->dateFrom = (string) ($saved['from'] ?? '');
                $this->dateTo = (string) ($saved['to'] ?? '');

                return;
            }
        }

        $this->fillRangeFromPreset($this->preset);
    }

    private function dateRangeSessionKey(): string
    {
        return 'date_range_bar.' . static::class;
    }

    private function rememberDateRange(): void
    {
        session([$this->dateRangeSessionKey() => [
            'day' => now()->toDateString(),
            'preset' => $this->preset,
            'from' => $this->dateFrom,
            'to' => $this->dateTo,
        ]]);
    }

    public function applyPreset(string $preset): void
    {
        $this->preset = $preset;

        if ($preset !== 'custom') {
            $this->fillRangeFromPreset($preset);
        }

        $this->rememberDateRange();
        $this->resetPage();
    }

    public function dateChanged(): void
    {
        $this->preset = 'custom';
        $this->rememberDateRange();
        $this->resetPage();
    }

    /** @return array<string, string> */
    public function getPresetOptions(): array
    {
        return FinanceDateRange::presetOptions();
    }

    protected function applyDateRange(Builder $query, string $column): Builder
    {
        return $query
            ->when($this->dateFrom, fn (Builder $q, string $d) => $q->whereDate($column, '>=', $d))
            ->when($this->dateTo, fn (Builder $q, string $d) => $q->whereDate($column, '<=', $d));
    }

    private function fillRangeFromPreset(string $preset): void
    {
        $range = FinanceDateRange::parseDateRange($preset);
        $this->dateFrom = $range['from'];
        $this->dateTo = $range['to'];
    }
}
