<?php

namespace App\Console\Commands;

use App\Services\Telegram\TelegramSender;
use Illuminate\Console\Command;

/**
 * php artisan telegram:test [chat_id] [--text="..."]
 *
 * Перевірка підключення бота: надсилає тестове повідомлення в чат (за замовчуванням TELEGRAM_DEFAULT_CHAT_ID).
 * Працює лише при TELEGRAM_ENABLED=true. Див. docs/modules/telegram.md.
 */
class TelegramTest extends Command
{
    protected $signature = 'telegram:test {chat? : Chat ID (за замовчуванням TELEGRAM_DEFAULT_CHAT_ID)} {--text= : Свій текст}';

    protected $description = 'Send a test message through the ERM Telegram bot';

    public function handle(TelegramSender $telegram): int
    {
        if (! $telegram->enabled()) {
            $this->error('Telegram вимкнено: перевір TELEGRAM_ENABLED=true і TELEGRAM_BOT_TOKEN у .env.');

            return self::FAILURE;
        }

        $text = $this->option('text') ?: '<b>ERM:</b> тестове повідомлення бота ('.now()->format('d.m.Y H:i').')';

        if ($telegram->send($text, $this->argument('chat'))) {
            $this->info('Надіслано.');

            return self::SUCCESS;
        }

        $this->error('Не надіслано. Деталі в storage/logs/laravel.log.');

        return self::FAILURE;
    }
}
