<?php

namespace App\Services\Finance;

/**
 * Date-range helper for the finance analytics page.
 *
 * Two-step flow:
 *   1. parseDateRange()       — UI preset/custom → neutral ['from','to'] in Y-m-d
 *   2. formatForCrm()         — Y-m-d → full datetime strings for MySQL WHERE
 *      formatForPrivatBank()  — Y-m-d → dd-mm-YYYY strings for the PrivatBank API
 *
 * Keeping this as a plain static helper (no constructor injection needed —
 * it only works with date strings, no external dependencies).
 */
class FinanceDateRange
{
    // ──────────────────────────────────────────────
    // Step 1 — parse UI input into a neutral range
    // ──────────────────────────────────────────────

    /**
     * Accept a preset name (or 'custom') plus optional manual dates from the UI.
     *
     * Presets:
     *   last_30_days  — default; rolling 30-day window ending today
     *   today         — today only
     *   current_month — 1st to last day of the current calendar month
     *   current_year  — Jan 1 to Dec 31 of the current year
     *   custom        — use $customFrom / $customTo directly (Y-m-d strings
     *                   from an HTML <input type="date">)
     *
     * @return array{from: string, to: string}  both dates in Y-m-d format
     */
    public static function parseDateRange(
        string $preset = 'last_30_days',
        ?string $customFrom = null,
        ?string $customTo = null,
    ): array {
        $today = now()->toDateString(); // Y-m-d

        return match ($preset) {
            'today'         => ['from' => $today, 'to' => $today],
            'yesterday'     => [
                'from' => now()->subDay()->toDateString(),
                'to'   => now()->subDay()->toDateString(),
            ],
            'last_7_days'   => [
                'from' => now()->subDays(7)->toDateString(),
                'to'   => $today,
            ],
            'current_month' => [
                'from' => now()->startOfMonth()->toDateString(),
                'to'   => now()->endOfMonth()->toDateString(),
            ],
            'prev_month'    => [
                'from' => now()->subMonthNoOverflow()->startOfMonth()->toDateString(),
                'to'   => now()->subMonthNoOverflow()->endOfMonth()->toDateString(),
            ],
            'current_year'  => [
                'from' => now()->startOfYear()->toDateString(),
                'to'   => now()->endOfYear()->toDateString(),
            ],
            'custom' => [
                'from' => $customFrom ?? now()->subDays(30)->toDateString(),
                'to'   => $customTo   ?? $today,
            ],
            default => [ // last_30_days
                'from' => now()->subDays(30)->toDateString(),
                'to'   => $today,
            ],
        };
    }

    // ──────────────────────────────────────────────
    // Step 2a — format for CRM (MySQL)
    // ──────────────────────────────────────────────

    /**
     * Convert a neutral range to full datetime strings for MySQL.
     *
     * Usage:
     *   ->whereBetween('paid_at', array_values(FinanceDateRange::formatForCrm($range)))
     *
     * @param  array{from: string, to: string}  $range  Y-m-d dates
     * @return array{from: string, to: string}  full Y-m-d H:i:s strings
     */
    public static function formatForCrm(array $range): array
    {
        return [
            'from' => $range['from'] . ' 00:00:00',
            'to'   => $range['to']   . ' 23:59:59',
        ];
    }

    // ──────────────────────────────────────────────
    // Step 2b — format for PrivatBank API
    // ──────────────────────────────────────────────

    /**
     * Convert a neutral range to the dd-mm-YYYY strings required by the
     * PrivatBank Business API (/statements/transactions, /statements/balance).
     *
     * Example: '2026-07-01' → '01-07-2026'
     *
     * @param  array{from: string, to: string}  $range  Y-m-d dates
     * @return array{startDate: string, endDate: string}
     */
    public static function formatForPrivatBank(array $range): array
    {
        return [
            'startDate' => \Carbon\Carbon::parse($range['from'])->format('d-m-Y'),
            'endDate'   => \Carbon\Carbon::parse($range['to'])->format('d-m-Y'),
        ];
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    /**
     * Human-readable label for a preset — shown alongside the date inputs
     * so the user always sees which period is active.
     */
    public static function presetLabel(string $preset): string
    {
        return match ($preset) {
            'today'         => 'Сьогодні',
            'current_month' => 'Поточний місяць',
            'current_year'  => 'Поточний рік',
            'custom'        => 'Довільний період',
            default         => 'Останні 30 днів',
        };
    }

    /** All presets for a <select> dropdown. */
    public static function presetOptions(): array
    {
        return [
            'last_30_days'  => 'Останні 30 днів',
            'today'         => 'Сьогодні',
            'yesterday'     => 'Вчора',
            'last_7_days'   => 'Останні 7 днів',
            'current_month' => 'Поточний місяць',
            'prev_month'    => 'Попередній місяць',
            'current_year'  => 'Поточний рік',
            'custom'        => 'Довільний період',
        ];
    }
}
