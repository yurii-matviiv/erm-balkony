{{--
    Status bar (clickable stage stepper) + action bar (one "what to do
    next" button) — rendered above the edit form via EditLead::getHeader().
    See that class's docblock for the reasoning.
--}}
@php
    $currentStage = $record->stage;
    $latestMeasurement = $record->latestMeasurement();
    $latestOrder = $record->latestOrder();
@endphp

<div class="space-y-3 mb-6">

    {{-- Статус-бар: один прямокутник на етап, поточний — підсвічений. --}}
    <div class="flex flex-wrap gap-1">
        @foreach ($stageOptions as $key => $label)
            <button
                type="button"
                wire:click="setStage({{ \Illuminate\Support\Js::from($key) }})"
                @class([
                    'px-3 py-2 text-xs font-medium rounded-md transition whitespace-nowrap',
                    'bg-primary-600 text-white' => $key === $currentStage,
                    'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700' => $key !== $currentStage,
                ])
            >
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- Action-бар: рівно одна підказка, що робити далі. Реалізовано два
         кроки (замір -> замовлення) — наступні кроки (КП, узгодження тощо)
         додамо окремо, етап за етапом. Після створення замовлення кнопка
         зникає — лишається тільки посилання на саме замовлення. --}}
    <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-3">
        @if (! $latestMeasurement)
            {{ $createMeasurementAction }}
        @elseif (! $latestOrder)
            <div class="flex items-center justify-between gap-3">
                <div class="text-sm text-gray-600 dark:text-gray-300">
                    <strong>Замір призначено:</strong>
                    {{ $latestMeasurement->scheduled_date->format('d.m.Y') }}
                    @if ($latestMeasurement->scheduled_time)
                        о {{ \Illuminate\Support\Carbon::parse($latestMeasurement->scheduled_time)->format('H:i') }}
                    @endif
                    — замірник: {{ $latestMeasurement->surveyor?->name }}
                    @if ($latestMeasurement->installer_id && $latestMeasurement->installer_id !== $latestMeasurement->surveyor_id)
                        , монтажник: {{ $latestMeasurement->installer?->name }}
                    @endif
                </div>

                {{ $createOrderAction }}
            </div>
        @else
            <div class="flex items-center justify-between gap-3">
                <div class="text-sm text-gray-600 dark:text-gray-300">
                    <strong>Замовлення створено:</strong>
                    №{{ $latestOrder->id }}, адреса: {{ $latestOrder->address }}
                </div>

                <x-filament::button
                    tag="a"
                    :href="\App\Filament\Resources\OrderResource::getUrl('edit', ['record' => $latestOrder])"
                    color="gray"
                    icon="heroicon-o-shopping-bag"
                >
                    Перейти до замовлення
                </x-filament::button>
            </div>
        @endif
    </div>

</div>
