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
    /* Кнопка «Прийняти» у списку заявок: тонка (1px) жовта смужка біжить по колу за годинниковою стрілкою. */
    @property --accept-angle { syntax: '<angle>'; initial-value: 0deg; inherits: false; }
    .accept-glow { display: inline-block; padding: 1px; border-radius: 6px;
        background: conic-gradient(from var(--accept-angle), transparent 0deg, transparent 270deg, #facc15 330deg, #fde047 360deg);
        animation: accept-glow-spin 2.4s linear infinite; }
    .accept-glow > * { display: inline-flex; }
    @keyframes accept-glow-spin { to { --accept-angle: 360deg; } }
</style>
