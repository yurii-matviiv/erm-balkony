{{--
    Єдиний статус-бар для всіх сутностей (лід, замовлення, ...). Завжди
    розміщується ВГОРУ сторінки. Стилі — клас .sb у head-хуку
    AdminPanelProvider (inline CSS: Tailwind-класи з нових blade не
    компілюються без npm run build).

    Props:
      steps    — [key => ['label' => string, 'date' => ?string]]
      current  — key поточного етапу
      click    — назва Livewire-методу (приймає key); null = лише показ
      extra    — [['label' => string, 'tone' => 'danger']] — окремі
                 індикатори в кінці (напр. «Відмінено»)
--}}
@props(['steps', 'current', 'click' => null, 'extra' => []])
@php
    $keys = array_keys($steps);
    $currentIndex = array_search($current, $keys, true);
    $currentIndex = $currentIndex === false ? -1 : $currentIndex;
    $dimmed = filled($extra); // скасовано/відхилено: прогрес приглушуємо
@endphp
<div class="sb {{ $dimmed ? 'sb-dimmed' : '' }}">
    @foreach ($steps as $key => $step)
        @php
            $state = $loop->index < $currentIndex ? 'done' : ($loop->index === $currentIndex ? 'current' : 'todo');
        @endphp
        <{{ $click ? 'button' : 'div' }} @if ($click) type="button" wire:click="{{ $click }}('{{ $key }}')" @endif class="sb-step sb-{{ $state }}">
            <span class="sb-label">{{ $step['label'] }}</span>
            @if (! empty($step['date']))
                <span class="sb-date">{{ $step['date'] }}</span>
            @endif
        </{{ $click ? 'button' : 'div' }}>
    @endforeach
    @foreach ($extra as $item)
        <div class="sb-step sb-{{ $item['tone'] ?? 'danger' }}"><span class="sb-label">{{ $item['label'] }}</span></div>
    @endforeach
</div>
