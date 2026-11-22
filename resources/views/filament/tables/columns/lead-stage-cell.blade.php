@php($record = $getRecord())
<div class="fi-ta-text" style="padding: 0.25rem 0.75rem;">
    @if (\App\Filament\Resources\LeadResource::hiddenUntilAccepted($record))
        {{-- Не статус, а справжня кнопка: заявку ще не прийнято. --}}
        <x-filament::button size="sm" icon="heroicon-m-check" wire:click.stop="acceptLead({{ $record->getKey() }})">
            Прийняти
        </x-filament::button>
    @else
        <span style="font-size:.875rem; line-height:1.25rem; font-weight:400;">{{ $getState() }}</span>
    @endif
</div>
