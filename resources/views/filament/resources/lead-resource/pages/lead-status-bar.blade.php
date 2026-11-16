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

<div class="mb-6" style="display:flex;flex-direction:column;gap:1.25rem;">

    {{-- Old CRM: manager-facing warning from the sales head, shown above the card. --}}
    @if (filled($record->comment_sales_head))
        <div class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-center text-amber-900">
            <div class="font-semibold">⚠️ Увага</div>
            <div class="mt-1 whitespace-pre-line">{{ $record->comment_sales_head }}</div>
        </div>
    @endif


    {{-- Статус-бар — єдиний компонент x-status-bar --}}
    <x-status-bar :steps="collect($stageOptions)->map(fn ($l) => ['label' => $l])->all()" :current="$currentStage" click="setStage" />

    {{-- Хто відповідальний за лід — видно всім ролям (раніше на картці ліда менеджера не було взагалі). --}}
    <div class="flex flex-wrap items-center justify-center gap-x-6 gap-y-1 rounded-lg bg-gray-50 dark:bg-gray-800 px-4 py-2 text-sm text-gray-500 dark:text-gray-400">
        <span>Менеджер: <strong class="text-gray-700 dark:text-gray-200">{{ $record->manager?->name ?? 'не призначено' }}</strong></span>
        <span>Створено: <strong class="text-gray-700 dark:text-gray-200">{{ $record->created_at?->format('d.m.Y') }}</strong></span>
    </div>

    {{-- Action-бар: рівно одна підказка, що робити далі. Реалізовано два
         кроки (замір -> замовлення) — наступні кроки (КП, узгодження тощо)
         додамо окремо, етап за етапом. Після створення замовлення кнопка
         зникає — лишається тільки посилання на саме замовлення. --}}
    @unless ($readOnly)
    <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-3">
        <div class="flex flex-wrap items-center gap-3">
            @if (! $latestMeasurement)
                {{ $createMeasurementAction }}
            @else
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
            @endif

            {{ $createReminderAction }}

            @if ($latestOrder)
                <x-filament::button
                    tag="a"
                    :href="\App\Filament\Resources\OrderResource::getUrl('edit', ['record' => $latestOrder])"
                    color="success"
                    icon="heroicon-o-shopping-bag"
                >
                    Перейти до замовлення №{{ $latestOrder->id }}
                </x-filament::button>
            @else
                {{ $createOrderAction }}
            @endif
        </div>
    </div>
    @endunless


    {{-- Нагадування (old CRM: table under the action bar) --}}
    <div>
        <h3 class="text-sm font-semibold text-gray-950 dark:text-white" style="margin-bottom:.5rem;">Нагадування</h3>
        @if ($reminders->isEmpty())
            <div class="text-sm text-gray-500">Немає нагадувань</div>
        @else
            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                <table class="w-full text-sm" style="border-collapse:collapse;">
                    <thead style="background:rgba(0,0,0,.04);text-align:left;">
                        <tr>
                            <th style="padding:.5rem .75rem;">Створено</th>
                            <th style="padding:.5rem .75rem;">Текст нагадування</th>
                            <th style="padding:.5rem .75rem;">Коли нагадати</th>
                            <th style="padding:.5rem .75rem;text-align:center;">Статус</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($reminders as $reminder)
                            <tr wire:key="reminder-{{ $reminder->id }}" style="border-top:1px solid rgba(0,0,0,.09);">
                                <td style="padding:.5rem .75rem;white-space:nowrap;">{{ $reminder->created_at->locale('uk')->isoFormat('dd DD.MM') }}</td>
                                <td style="padding:.5rem .75rem;width:50%;">
                                    <textarea rows="2" class="fi-input" style="width:100%;border:1px solid rgba(0,0,0,.15);border-radius:3px;padding:.35rem .5rem;"
                                        wire:blur="updateReminderField({{ $reminder->id }}, 'reminder_text', $event.target.value)">{{ $reminder->reminder_text }}</textarea>
                                </td>
                                <td style="padding:.5rem .75rem;">
                                    <input type="datetime-local" value="{{ $reminder->reminder_datetime->format('Y-m-d\TH:i') }}"
                                        style="border:1px solid rgba(0,0,0,.15);border-radius:3px;padding:.35rem .5rem;"
                                        wire:change="updateReminderField({{ $reminder->id }}, 'reminder_datetime', $event.target.value)">
                                </td>
                                <td style="padding:.5rem .75rem;text-align:center;white-space:nowrap;">
                                    <label style="display:inline-flex;align-items:center;gap:.5rem;cursor:pointer;">
                                        <input type="checkbox" @checked($reminder->is_completed)
                                            wire:change="updateReminderField({{ $reminder->id }}, 'is_completed', $event.target.checked)">
                                        {{ $reminder->is_completed ? 'виконано' : 'активне' }}
                                    </label>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
