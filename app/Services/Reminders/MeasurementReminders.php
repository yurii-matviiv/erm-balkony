<?php

namespace App\Services\Reminders;

use App\Models\LeadMeasurement;
use App\Models\ManagerReminder;
use Illuminate\Support\Carbon;

/**
 * Creates the automatic reminders for one measurement appointment (tab «Заміри»).
 * Rules live in config/measurement_reminders.php; all times are Kyiv wall-clock time,
 * stored as-is in manager_reminders.reminder_datetime (same as manually created reminders).
 */
class MeasurementReminders
{
    public function create(LeadMeasurement $measurement): void
    {
        $lead = $measurement->lead()->with('client')->first();
        $userId = $lead?->manager_id;

        // Nobody to remind: a lead without a manager.
        if (! $userId) {
            return;
        }

        $cfg = config('measurement_reminders');
        $tz = $cfg['timezone'];
        $now = now($tz);

        $date = Carbon::parse($measurement->scheduled_date->toDateString(), $tz);
        $time = $measurement->scheduled_time ? substr((string) $measurement->scheduled_time, 0, 5) : null;
        $start = $time ? Carbon::parse($date->toDateString().' '.$time, $tz) : null;

        // 1) Evening before.
        $evening = Carbon::parse($date->copy()->subDay()->toDateString().' '.$cfg['evening_before_at'], $tz);

        // 2) Day of the measurement: N hours before, but not earlier than the configured hour.
        $earliest = Carbon::parse($date->toDateString().' '.$cfg['day_of_earliest'], $tz);
        $dayOf = $start ? $start->copy()->subHours($cfg['day_of_hours_before'])->max($earliest) : $earliest;
        // A measurement at/before the earliest hour has no useful same-day reminder (the evening one covers it).
        $dayOfUseful = ! $start || $start->gt($earliest);

        $when = collect([[$evening, 'evening']]);
        if ($dayOfUseful) {
            $when->push([$dayOf, 'day']);
        }

        $future = $when->filter(fn (array $w): bool => $w[0]->gt($now));

        // Both moments already passed (measurement created at the last minute) but the measurement itself is still ahead:
        // remind right away, so the manager is not left without any reminder.
        if ($future->isEmpty() && ($start ? $start->gt($now) : $date->endOfDay()->gt($now))) {
            $future = collect([[$now->copy()->addMinute(), 'now']]);
        }

        $clientName = $lead->client?->full_name ?? 'клієнт не вказаний';
        $address = $lead->site_address ?: $lead->client?->full_address ?: 'адреса не вказана';
        $surveyor = $measurement->surveyor?->name;

        foreach ($future as [$at, $kind]) {
            ManagerReminder::create([
                'user_id' => $userId,
                'lead_id' => $lead->id,
                'lead_measurement_id' => $measurement->id,
                'kind' => 'measurement',
                'reminder_type' => 'important_urgent',
                'reminder_datetime' => $at->format('Y-m-d H:i:s'),
                'reminder_text' => $this->text($kind, $date, $time, $clientName, $address, $surveyor, $now),
            ]);
        }
    }

    private function text(string $kind, Carbon $date, ?string $time, string $client, string $address, ?string $surveyor, Carbon $now): string
    {
        $dayWord = match (true) {
            $date->isSameDay($now) => 'Сьогодні',
            $date->isSameDay($now->copy()->addDay()) => 'Завтра',
            default => $date->locale('uk')->isoFormat('dd DD.MM'),
        };
        // «Завтра»/«Сьогодні» is computed at SEND time, not at creation: store the date neutrally for evening/day reminders.
        $dayWord = match ($kind) {
            'evening' => 'Завтра',
            'day' => 'Сьогодні',
            default => $dayWord,
        };

        $whenPart = $time ? "о {$time}" : '(час не вказано)';
        $who = $surveyor ? ", замірник: {$surveyor}" : '';

        return "Замір: {$dayWord} {$whenPart}. Клієнт: {$client}, адреса: {$address}{$who}.";
    }
}
