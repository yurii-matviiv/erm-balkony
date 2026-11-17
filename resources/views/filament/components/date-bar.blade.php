{{-- Standard date bar (see App\Filament\Concerns\HasDateRangeBar). Always visible, page-level. --}}
@php
    // Flat, almost-rectangular fields without shadow — same look as the fields inside a lead card.
    $dbInput = 'block bg-white px-3 py-2 text-sm text-gray-950 dark:bg-gray-800 dark:text-gray-200';
    $dbStyle = 'border:1px solid #cfd8de;border-radius:3px;box-shadow:none;outline:none;height:2.5rem;';
@endphp
{{-- Plain wrapper on purpose: no card border/background around the bar, so it does not add a box to the page structure. --}}
<div>
    <div class="flex flex-wrap items-end gap-4">
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-gray-500 dark:text-gray-400">Період</label>
            <select wire:change="applyPreset($event.target.value)" class="{{ $dbInput }}" style="{{ $dbStyle }}">
                @foreach ($this->getPresetOptions() as $value => $label)
                    <option value="{{ $value }}" @selected($preset === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-gray-500 dark:text-gray-400">З</label>
            <input type="date" wire:model.live="dateFrom" wire:change="dateChanged" value="{{ $dateFrom }}" class="{{ $dbInput }}" style="{{ $dbStyle }}">
        </div>

        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-gray-500 dark:text-gray-400">По</label>
            <input type="date" wire:model.live="dateTo" wire:change="dateChanged" value="{{ $dateTo }}" class="{{ $dbInput }}" style="{{ $dbStyle }}">
        </div>
    </div>
</div>
