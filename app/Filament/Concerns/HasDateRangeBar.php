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
    public string $preset = 'last_30_days';

    public string $dateFrom = '';

    public string $dateTo = '';

    public function mountHasDateRangeBar(): void
    {
        $this->fillRangeFromPreset($this->preset);
    }

    public function applyPreset(string $preset): void
    {
        $this->preset = $preset;

        if ($preset !== 'custom') {
            $this->fillRangeFromPreset($preset);
        }

        $this->resetPage();
    }

    public function dateChanged(): void
    {
        $this->preset = 'custom';
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
