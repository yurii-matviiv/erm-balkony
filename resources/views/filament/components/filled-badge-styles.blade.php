{{-- Global look of status cells (->filledBadge()). Edit here once for every table. --}}
<style>
    /* Fixed height of the fill, same in every row. Change it here only. */
    :root { --filled-badge-height: calc(1.5rem + 10px); }
    td.filled-badge-cell { position: relative; }
    td.filled-badge-cell::before {
        content: ''; position: absolute; left: 4px; right: 4px; top: 50%; height: var(--filled-badge-height); transform: translateY(-50%); pointer-events: none;
        background-color: color-mix(in srgb, var(--badge-color) 22%, transparent);
    }
    td.filled-badge-cell > * { position: relative; }

    /* ONE look for every Filament ->badge() inside tables (service types, payment
       source, roles, ...): same as the filled status cell — flat rectangle (3px),
       regular weight (як звичайний текст таблиці), light tint of the badge colour, dark text. Edit here only. */
    .fi-ta-table .fi-badge {
        border-radius: 3px; box-shadow: none; font-size: .875rem; font-weight: 400; line-height: 1.25rem;
        padding: .25rem .625rem; min-height: calc(1.5rem + 10px); color: inherit;
        background-color: color-mix(in srgb, var(--color-500, var(--gray-500)) 22%, transparent);
    }
    .fi-ta-table .fi-badge .fi-badge-label { color: inherit; }
</style>
