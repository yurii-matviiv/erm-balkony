{{-- Standard single-select bar (docs/ui/standards.md §4.3). Same look and position as the date bar:
     small grey label above a flat 2.5rem field, 3px radius, no shadow. Params: $label, $model, $options. --}}
@php
    $dbInput = 'block bg-white px-3 py-2 text-sm text-gray-950 dark:bg-gray-800 dark:text-gray-200';
    $dbStyle = 'border:1px solid #cfd8de;border-radius:3px;box-shadow:none;outline:none;height:2.5rem;';
@endphp
<div>
    <div class="flex flex-wrap items-end gap-4">
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $label }}</label>
            <select wire:model.live="{{ $model }}" class="{{ $dbInput }}" style="{{ $dbStyle }}">
                @foreach ($options as $value => $optionLabel)
                    <option value="{{ $value }}">{{ $optionLabel }}</option>
                @endforeach
            </select>
        </div>
    </div>
</div>
