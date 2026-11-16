{{--
    Compact summary bar above the edit form — modeled on the OLD system's
    order page (read-only reference in dev.ERM-btv / the live old CRM
    screenshot the user shared), which puts "what is this, what state is
    it in, who's running it" front and center before any editable field.
    Purely informational — every value shown here is also editable in the
    form sections below; this is just a friendlier summary on top.
--}}
<div class="mb-6 space-y-3">

    {{-- STANDARD: who owns this record, when it was created and the order type — always the very first strip. --}}
    <div style="justify-content:flex-end;" class="flex flex-wrap items-center gap-x-6 gap-y-2 rounded-lg bg-gray-50 dark:bg-gray-800 px-4 py-2 text-sm text-gray-500 dark:text-gray-400">
        <span>Менеджер: <strong class="text-gray-700 dark:text-gray-200">{{ $record->manager?->name ?? '—' }}</strong></span>
        <span>Створено: <strong class="text-gray-700 dark:text-gray-200">{{ $record->created_at?->locale('uk')->isoFormat('dd DD.MM.YYYY HH:mm') }}</strong></span>
        <span>Консультант: <strong class="text-gray-700 dark:text-gray-200">{{ $record->consultant?->name ?? 'немає' }}</strong></span>
        @if ($record->lead_id)
            <a href="{{ \App\Filament\Resources\LeadResource::getUrl('edit', ['record' => $record->lead_id]) }}" class="text-primary-600 hover:underline">
                Лід №{{ $record->lead_id }}
            </a>
        @endif

        <div style="display:flex; align-items:center; gap:0.5rem;">
            <span>Тип замовлення:</span>
            @if ($canChange)
                <select wire:change="setOrderType($event.target.value)"
                        style="border:1px solid #cfd8de;border-radius:3px;box-shadow:none;outline:none;min-width:14rem;"
                        class="bg-white px-2 py-1 text-sm text-gray-800 dark:bg-gray-900 dark:text-gray-100">
                    <option value="" @selected(! $record->order_type)>— не вказано —</option>
                    @foreach ($orderTypeOptions as $key => $label)
                        <option value="{{ $key }}" @selected($record->order_type === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            @else
                <strong class="text-gray-700 dark:text-gray-200">{{ $orderTypeLabel ?? '—' }}</strong>
            @endif
        </div>
    </div>

    @if ($record->isLegacy())
        <div class="flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 dark:bg-amber-950/30 dark:border-amber-800 px-4 py-2 text-sm text-amber-700 dark:text-amber-400">
            <x-filament::icon icon="heroicon-o-archive-box" class="h-4 w-4 shrink-0" />
            <span>Імпортовано зі старої системи (ID: {{ $record->legacy_id }}). Дані можуть бути неповними або потребувати уточнення.</span>
        </div>
    @endif

    <x-status-bar :steps="$steps" :current="$record->stage" :click="$canChange ? 'setStage' : null" :extra="$extra" />

</div>
