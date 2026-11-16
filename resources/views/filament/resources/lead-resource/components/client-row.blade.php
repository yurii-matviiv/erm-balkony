<div class="record-row" style="display:grid;grid-template-columns:minmax(0,1fr) minmax(0,2fr);gap:1rem;align-items:center;">
    <span class="text-sm font-medium text-gray-950 dark:text-white">ПІБ</span>
    <div class="text-sm" style="display:flex;align-items:center;gap:16px;width:100%;">
        <span class="text-gray-950 dark:text-white">{{ $record?->client?->full_name ?? '—' }}</span>
        <div style="margin-left:auto;">{{ $editClientAction }}</div>
    </div>
</div>
