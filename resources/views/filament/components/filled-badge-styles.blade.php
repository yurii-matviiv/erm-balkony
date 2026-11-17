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
</style>
