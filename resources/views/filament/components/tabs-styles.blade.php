{{-- Global look of tabs (every Filament tab bar, all panels): rectangular tabs on ONE continuous line,
     the active tab has a blue bar. Edit here once. --}}
<style>
    .fi-tabs:not(.fi-vertical) {
        display: flex;
        gap: 0;
        align-items: flex-end;
        padding: 0;
        margin: 0;
        max-width: none;
        overflow-x: auto;
        background: transparent;
        border-radius: 0;
        box-shadow: none;
        --tw-ring-shadow: 0 0 #0000;
        border-bottom: 1px solid var(--gray-300);
    }
    .fi-tabs:not(.fi-vertical) .fi-tabs-item {
        position: relative;
        margin-bottom: -1px;               /* sits on the continuous line */
        padding: .7rem 1.25rem;
        border-radius: 0;                  /* rectangular */
        border: 1px solid var(--gray-200);
        border-bottom: 3px solid transparent;
        background: var(--gray-100);
        color: var(--gray-600);
        font-weight: 500;
        box-shadow: none;
    }
    .fi-tabs:not(.fi-vertical) .fi-tabs-item + .fi-tabs-item { border-left-width: 0; }
    .fi-tabs:not(.fi-vertical) .fi-tabs-item:hover { background: var(--gray-50); color: var(--primary-700); }
    .fi-tabs:not(.fi-vertical) .fi-tabs-item.fi-active {
        background: #fff;
        color: var(--primary-700);
        font-weight: 600;
        border-bottom-color: var(--primary-600);   /* blue bar of the active tab */
    }
    .fi-tabs:not(.fi-vertical) .fi-tabs-item .fi-icon,
    .fi-tabs:not(.fi-vertical) .fi-tabs-item .fi-tabs-item-label { color: inherit; }

    .dark .fi-tabs:not(.fi-vertical) { border-bottom-color: rgb(255 255 255 / .15); }
    .dark .fi-tabs:not(.fi-vertical) .fi-tabs-item {
        background: var(--gray-800);
        border-color: rgb(255 255 255 / .1);
        border-bottom-color: transparent;
        color: var(--gray-300);
    }
    .dark .fi-tabs:not(.fi-vertical) .fi-tabs-item:hover { background: var(--gray-700); color: #fff; }
    .dark .fi-tabs:not(.fi-vertical) .fi-tabs-item.fi-active {
        background: var(--gray-900);
        color: var(--primary-300);
        border-bottom-color: var(--primary-500);
    }
</style>
