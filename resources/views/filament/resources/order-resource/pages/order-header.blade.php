{{--
    Compact summary bar above the edit form — modeled on the OLD system's
    order page (read-only reference in dev.ERM-btv / the live old CRM
    screenshot the user shared), which puts "what is this, what state is
    it in, who's running it" front and center before any editable field.
    Purely informational — every value shown here is also editable in the
    form sections below; this is just a friendlier summary on top.
--}}
<div class="mb-6 space-y-3">

    @if ($record->isLegacy())
        <div class="flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 dark:bg-amber-950/30 dark:border-amber-800 px-4 py-2 text-sm text-amber-700 dark:text-amber-400">
            <x-filament::icon icon="heroicon-o-archive-box" class="h-4 w-4 shrink-0" />
            <span>Імпортовано зі старої системи (ID: {{ $record->legacy_id }}). Дані можуть бути неповними або потребувати уточнення.</span>
        </div>
    @endif

    <div class="flex flex-wrap items-center justify-center gap-2 text-center">
        <x-filament::icon icon="heroicon-o-shopping-bag" class="h-7 w-7 text-gray-400" />
        <h2 class="text-xl font-semibold">Замовлення №{{ $record->id }}</h2>
    </div>

    <div class="flex flex-wrap items-center justify-center gap-2">
        <x-filament::badge :color="$stageColor" size="lg">{{ $stageLabel }}</x-filament::badge>

        @if ($orderTypeLabel)
            <x-filament::badge color="gray" size="lg">{{ $orderTypeLabel }}</x-filament::badge>
        @endif
    </div>

    <div class="flex flex-wrap items-center justify-center gap-x-6 gap-y-1 rounded-lg bg-gray-50 dark:bg-gray-800 px-4 py-2 text-sm text-gray-500 dark:text-gray-400">
        <span>Менеджер: <strong class="text-gray-700 dark:text-gray-200">{{ $record->manager?->name ?? '—' }}</strong></span>
        <span>Створено: <strong class="text-gray-700 dark:text-gray-200">{{ $record->created_at?->format('d.m.Y') }}</strong></span>
        <span>Консультант: <strong class="text-gray-700 dark:text-gray-200">{{ $record->consultant?->name ?? 'немає' }}</strong></span>
        @if ($record->lead_id)
            <a href="{{ \App\Filament\Resources\LeadResource::getUrl('edit', ['record' => $record->lead_id]) }}" class="text-primary-600 hover:underline">
                Лід №{{ $record->lead_id }}
            </a>
        @endif
    </div>

</div>
