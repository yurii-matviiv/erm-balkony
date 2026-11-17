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

    {{-- Page heading: getHeader() replaces Filament's whole header, so the title must be rendered here. --}}
    <h1 class="fi-header-heading" style="margin:0;">Заявка №{{ $record->id }}</h1>

    {{-- STANDARD: who owns this record + when it was created — always the very first strip on lead and order pages. --}}
    <div style="justify-content:flex-end;padding:.65rem 1rem;" class="flex flex-wrap items-center gap-x-6 gap-y-1 rounded-lg bg-gray-50 dark:bg-gray-800 px-4 py-2 text-sm text-gray-500 dark:text-gray-400">
        <span>Менеджер: <strong class="text-gray-700 dark:text-gray-200">{{ $record->manager?->name ?? 'не призначено' }}</strong></span>
        <span>Створено: <strong class="text-gray-700 dark:text-gray-200">{{ $record->created_at?->locale('uk')->isoFormat('dd DD.MM.YYYY HH:mm') }}</strong></span>
        @if ($latestOrder && auth()->user()?->can('View:Order'))
            <a href="{{ \App\Filament\Resources\OrderResource::getUrl('edit', ['record' => $latestOrder]) }}" class="font-medium text-primary-600 hover:underline">
                Перейти до замовлення №{{ $latestOrder->id }}
            </a>
        @endif
    </div>

    {{-- Швидкість реакції: від створення заявки до першого вихідного дзвінка клієнту (крок first_call, див. FirstCallDetector). --}}
    @php
        $firstCall = $record->steps()->where('step', 'first_call')->where('state', 'done')->first();
        $contactStep = $record->steps()->where('step', 'contacted')->where('state', 'done')->whereNotNull('occurred_at')->first();
        $fmt = function (int $min): string {
            if ($min < 1) return 'менше хвилини';
            if ($min < 60) return $min.' хв';
            if ($min < 1440) return intdiv($min, 60).' год '.($min % 60).' хв';
            return intdiv($min, 1440).' дн '.intdiv($min % 1440, 60).' год';
        };
        $reaction = null;
        if ($firstCall?->occurred_at) {
            $secs = \App\Services\Leads\FirstCallDetector::createdAt($record)->diffInSeconds($firstCall->occurred_at, false);
            $reaction = ['min' => max(0, (int) floor($secs / 60)), 'done' => true, 'before' => $secs < 0];
        } elseif (! $firstCall && $record->created_at) {
            $reaction = ['min' => max(0, (int) floor(\App\Services\Leads\FirstCallDetector::createdAt($record)->diffInMinutes(now()))), 'done' => false];
        }
        $color = $reaction ? ($reaction['min'] <= 15 ? '#1e8449' : ($reaction['min'] <= 60 ? '#b9770e' : '#c0392b')) : null;
    @endphp
    @if ($reaction)
        <div style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;padding:.65rem 1rem;border-radius:.5rem;border-left:4px solid {{ $color }};background:{{ $color }}14;font-size:.875rem;">
            <strong style="color:{{ $color }};">Швидкість реакції</strong>
            @if ($reaction['done'])
                <span>перший дзвінок клієнту @if ($reaction['before']) <strong>до створення заявки в системі</strong> @else через <strong>{{ $fmt($reaction['min']) }}</strong> після створення заявки @endif
                    ({{ $firstCall->occurred_at->copy()->setTimezone('Europe/Kyiv')->locale('uk')->isoFormat('dd DD.MM HH:mm') }})</span>
                @if ($contactStep?->occurred_at)
                    <span style="color:#1e8449;">· контакт: клієнт відповів {{ $contactStep->occurred_at->copy()->setTimezone('Europe/Kyiv')->locale('uk')->isoFormat('dd DD.MM HH:mm') }}</span>
                @elseif (in_array($record->stage, \App\Models\Lead::PRE_QUALIFIED_STAGES, true))
                    <span style="color:#c0392b;font-weight:600;">· спроба невдала: клієнт не відповів, контакту ще немає</span>
                @endif
            @else
                <span>дзвінка клієнту ще не було, минуло <strong>{{ $fmt($reaction['min']) }}</strong></span>
            @endif
        </div>
    @endif

    {{-- Old CRM: manager-facing warning from the sales head, shown above the card. --}}
    @if (filled($record->comment_sales_head))
        <div class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-center text-amber-900">
            <div class="font-semibold">⚠️ Увага</div>
            <div class="mt-1 whitespace-pre-line">{{ $record->comment_sales_head }}</div>
        </div>
    @endif


    {{-- Статус-бар — єдиний компонент x-status-bar --}}
    {{-- Нова заявка без менеджера: замість статус-бару кнопка «Прийняти заявку»; решта рядків розмита й неактивна, доки заявку не прийнято. --}}
    @php($unaccepted = $record->manager_id === null && ! $readOnly)
    @if ($unaccepted)
        <div style="display:flex;align-items:center;justify-content:center;gap:1rem;flex-wrap:wrap;padding:1rem;border:2px dashed #2874A6;border-radius:6px;background:#eaf2f8;">
            <span style="font-size:.95rem;color:#1b4f72;">Заявка ще не прийнята менеджером.</span>
            {{ $acceptLeadAction }}
        </div>
    @else
        <x-status-bar :steps="collect($stageOptions)->map(fn ($l) => ['label' => $l])->all()" :current="$currentStage" click="setStage" />
    @endif

    {{-- Action-бар: рівно одна підказка, що робити далі. Реалізовано два
         кроки (замір -> замовлення) — наступні кроки (КП, узгодження тощо)
         додамо окремо, етап за етапом. Після створення замовлення кнопка
         зникає — лишається тільки посилання на саме замовлення. --}}
    @unless ($readOnly)
    <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-3" @if ($unaccepted) style="filter:blur(2px);opacity:.55;pointer-events:none;user-select:none;" aria-hidden="true" @endif>
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

            <div style="margin-left:auto;">{{ $stagesInstructionAction }}</div>
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
