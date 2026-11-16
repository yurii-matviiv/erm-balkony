@php
    $address = $record?->site_address;
    $mapQuery = $address ?: $record?->client?->full_address;
@endphp
<div class="record-row" style="display:grid;grid-template-columns:minmax(0,1fr) minmax(0,2fr);gap:1rem;align-items:center;">
    <div class="text-sm" style="display:flex;flex-wrap:wrap;align-items:center;gap:4px 8px;">
        <span class="font-medium text-gray-950 dark:text-white">Адреса</span>
        @if ($mapQuery)
            <span class="text-gray-400">·</span>
            <a class="font-medium text-primary-600 hover:underline" target="_blank"
               href="https://www.google.com/maps/search/?api=1&query={{ urlencode($mapQuery) }}">Відкрити на мапі</a>
        @endif
    </div>
    <div class="text-sm" style="display:flex;align-items:center;gap:16px;width:100%;">
        <span class="text-gray-950 dark:text-white">{{ $address ?: '—' }}</span>
        <div style="margin-left:auto;">{{ $editAddressAction }}</div>
    </div>
</div>
