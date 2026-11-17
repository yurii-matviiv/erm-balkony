{{-- Global look of table headers (every Filament table, every panel). Edit colours here once. --}}
<style>
    /* Light tint of the brand blue (same hue as the sidebar icons). */
    :root {
        --table-header-bg: var(--primary-100);
        --table-header-fg: var(--primary-900);
        --table-header-line: var(--primary-200);
        /* Row under the cursor: header tint + a bar on the left of the first cell. */
        --table-row-hover-bg: var(--table-header-bg);
        --table-row-hover-bar: var(--primary-400);
    }
    .dark {
        --table-header-bg: color-mix(in srgb, var(--primary-500) 22%, var(--gray-900));
        --table-header-fg: var(--primary-100);
        --table-header-line: rgb(255 255 255 / .1);
        --table-row-hover-bar: var(--primary-400);
    }

    .fi-ta-table thead tr,
    .fi-ta-table thead th {
        background-color: var(--table-header-bg);
    }
    .fi-ta-table thead th,
    .fi-ta-table thead th .fi-ta-header-cell-label,
    .fi-ta-table thead th button,
    .fi-ta-table thead th span {
        color: var(--table-header-fg);
    }

    /* Hover highlight of the current row (data rows only, not group/summary rows). */
    .fi-ta-table tbody tr.fi-ta-row:not(.fi-ta-group-header-row):not(.fi-ta-summary-row):not(.fi-ta-summary-header-row):hover > td {
        background-color: var(--table-row-hover-bg);
    }
    .fi-ta-table tbody tr.fi-ta-row:not(.fi-ta-group-header-row):not(.fi-ta-summary-row):not(.fi-ta-summary-header-row):hover > td:first-child {
        box-shadow: inset 4px 0 0 var(--table-row-hover-bar);
    }
</style>
