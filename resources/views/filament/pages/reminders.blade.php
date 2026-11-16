<x-filament-panels::page>

    {{-- Tab bar: a line across the top; one button per reminder type. --}}
    <div style="display:flex;gap:.25rem;border-bottom:1px solid #cfd8de;margin-bottom:1rem;">
        @foreach ($this->getTabs() as $key => $label)
            @php $active = $this->activeTab === $key; $open = $this->openCount($key); @endphp
            <button type="button" wire:click="setTab('{{ $key }}')"
                style="padding:.6rem 1.1rem;font-size:.9rem;cursor:pointer;background:none;border:0;border-bottom:3px solid {{ $active ? '#2874A6' : 'transparent' }};margin-bottom:-1px;font-weight:{{ $active ? '600' : '400' }};color:{{ $active ? '#2874A6' : 'inherit' }};">
                {{ $label }}@if ($open > 0) <span style="margin-left:.35rem;font-size:.75rem;background:#2874A6;color:#fff;border-radius:3px;padding:1px 6px;">{{ $open }}</span>@endif
            </button>
        @endforeach
    </div>

    {{ $this->table }}

</x-filament-panels::page>
