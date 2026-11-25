<?php

namespace App\Console\Commands;

use App\Models\AppSetting;
use App\Services\Binotel\MissedCallsService;
use App\Services\Telegram\TelegramSender;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Telegram-сповіщення про незакриті пропущені дзвінки. Викликається планувальником кожні 5 хв,
 * але сама тримає робочі години й інтервал. Нічого не шле, якщо незакритих дзвінків немає.
 * docs/modules/missed-calls.md
 */
class NotifyMissedCalls extends Command
{
    protected $signature = 'calls:missed-notify {--force : ігнорувати вимикач, години та інтервал} {--dry : лише показати текст, нічого не надсилати}';

    protected $description = 'Telegram: незакриті пропущені дзвінки Binotel (раз на годину в робочий час)';

    public function handle(MissedCallsService $service, TelegramSender $telegram): int
    {
        $manual = $this->option('force') || $this->option('dry');
        $now = CarbonImmutable::now(config('missed_calls.timezone'));

        if (! $manual) {
            if (! config('missed_calls.enabled')) {
                return self::SUCCESS;
            }
            if ($now->hour < config('missed_calls.hour_from') || $now->hour > config('missed_calls.hour_to')) {
                return self::SUCCESS;
            }
            $lastRun = AppSetting::get('missed_calls_last_run_at');
            // -2 хв: щоб сітка планувальника по 5 хв не зсувала відправку щогодини на пізніший час.
            if ($lastRun && CarbonImmutable::parse($lastRun)->addMinutes(config('missed_calls.interval_minutes') - 2)->isFuture()) {
                return self::SUCCESS;
            }
        }

        try {
            $items = $service->unresolved($now);
        } catch (\Throwable $e) {
            Log::warning('MissedCalls: перевірка не вдалася', ['error' => $e->getMessage()]);
            $this->error('Перевірка не вдалася: '.$e->getMessage());

            return self::FAILURE; // last_run не оновлюємо: наступні 5 хв спробуємо знову
        }

        $text = $service->message($items, $now);

        if ($this->option('dry')) {
            $this->line($text !== '' ? $text : 'Незакритих пропущених немає: нічого б не надсилали.');

            return self::SUCCESS;
        }

        if ($text === '') {
            AppSetting::set('missed_calls_last_run_at', $now->toIso8601String());

            return self::SUCCESS;
        }

        if ($telegram->send($text)) {
            AppSetting::set('missed_calls_last_run_at', $now->toIso8601String());
            $this->info('Надіслано в Telegram: '.count($items).' дзвінків.');

            return self::SUCCESS;
        }

        $this->error('Telegram не прийняв повідомлення (див. storage/logs).');

        return self::FAILURE;
    }
}
