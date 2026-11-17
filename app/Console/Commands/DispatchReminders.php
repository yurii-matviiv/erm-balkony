<?php

namespace App\Console\Commands;

use App\Models\ManagerReminder;
use App\Services\WebPushSender;
use App\Support\RolePanels;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Every minute: sends Web Push for due reminders (docs/SCHEDULER.md, «reminders:dispatch»).
 *
 *  - kind «lead»        : one push when reminder_datetime comes (retried a few times only if sending failed);
 *  - kind «measurement» : push when due, then repeated every N minutes until the manager presses «Побачив»
 *                         (or marks it done) — at most `max_repeats` repeats. See config/measurement_reminders.php.
 *
 * reminder_datetime is Kyiv wall-clock time, so "due" is compared with the Kyiv clock, not with UTC.
 */
class DispatchReminders extends Command
{
    protected $signature = 'reminders:dispatch';

    protected $description = 'Надіслати push-нагадування менеджерам (щохвилини з планувальника)';

    public function handle(WebPushSender $sender): int
    {
        $cfg = config('measurement_reminders');
        $kyivNow = now($cfg['timezone'])->toDateTimeString();
        $repeatBefore = now()->subMinutes($cfg['repeat_interval_minutes']);
        $maxSends = 1 + $cfg['max_repeats'];

        // Cheap early exit (one indexed query): nothing due -> done in milliseconds.
        $due = ManagerReminder::query()
            ->where('is_completed', false)
            ->whereNull('acknowledged_at')
            ->where('reminder_datetime', '<=', $kyivNow)
            // Stale guard: never fire reminders that are more than a day overdue (e.g. old ones arriving via sync:legacy).
            ->where('reminder_datetime', '>=', now($cfg['timezone'])->subDay()->toDateTimeString())
            ->where(function ($q) use ($repeatBefore, $maxSends): void {
                $q->where(fn ($q) => $q->where('kind', 'lead')->whereNull('notified_at')->where('notify_count', '<', 5))
                    ->orWhere(fn ($q) => $q->where('kind', 'measurement')
                        ->where('notify_count', '<', $maxSends)
                        ->where(fn ($q) => $q->whereNull('notified_at')->orWhere('notified_at', '<=', $repeatBefore)));
            })
            ->with('user', 'lead.client')
            ->limit(200)
            ->get();

        if ($due->isEmpty()) {
            return self::SUCCESS;
        }

        foreach ($due as $reminder) {
            $user = $reminder->user;

            if (! $user || ! $user->is_active) {
                $reminder->update(['notified_at' => now(), 'notify_count' => 99]);

                continue;
            }

            $isMeasurement = $reminder->kind === 'measurement';
            $title = $isMeasurement
                ? ($reminder->notify_count > 0 ? 'Повторно: нагадування про замір' : 'Нагадування про замір')
                : 'Нагадування';

            $result = $sender->sendToUser($user, $title, $reminder->reminder_text, $this->url($reminder));

            // No subscribed browser at all: nothing to retry, the in-app badge/banner is the fallback.
            $delivered = $result['sent'] > 0 || $result['subscriptions'] === 0;

            if (! $delivered) {
                Log::warning('reminders:dispatch — push не надіслано', ['reminder' => $reminder->id, 'errors' => $result['errors']]);
            }

            $reminder->update([
                'notify_count' => $reminder->notify_count + 1,
                // Lead reminders: stay "not notified" while sending fails (retried, max 5 tries); measurement: always delay by the interval.
                'notified_at' => ($delivered || $isMeasurement) ? now() : null,
            ]);
        }

        return self::SUCCESS;
    }

    private function url(ManagerReminder $reminder): string
    {
        $panel = $reminder->user ? RolePanels::idForUser($reminder->user) : null;

        try {
            return \App\Filament\Pages\Reminders::getUrl(panel: $panel === RolePanels::MANAGER ? 'manager' : 'admin');
        } catch (\Throwable) {
            return url('/');
        }
    }
}
