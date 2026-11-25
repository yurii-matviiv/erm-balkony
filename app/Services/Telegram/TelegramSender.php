<?php

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Єдине місце відправки повідомлень у Telegram з ERM (docs/modules/telegram.md).
 * Нові сповіщення (пропущені дзвінки тощо) викликають лише цей клас, а не пишуть власних запитів до Telegram.
 *
 * Ніколи не кидає виняток: збій Telegram не повинен ламати основну дію (збереження, синхронізацію).
 * Повертає true лише коли Telegram підтвердив прийом повідомлення.
 */
class TelegramSender
{
    /** Telegram у режимі HTML приймає тільки ці теги. */
    private const ALLOWED_TAGS = ['b', 'i', 'u', 'a', 'code', 'pre'];

    public function enabled(): bool
    {
        return (bool) config('telegram.enabled') && filled(config('telegram.token'));
    }

    /** Відправити HTML-текст у чат. $chatId = null → чат за замовчуванням. */
    public function send(string $html, string|int|null $chatId = null): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        $chatId ??= config('telegram.default_chat');
        if (blank($chatId)) {
            Log::warning('Telegram: чат не вказано (TELEGRAM_DEFAULT_CHAT_ID порожній), повідомлення не надіслано');

            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout((int) config('telegram.timeout', 5))
                ->post('https://api.telegram.org/bot'.config('telegram.token').'/sendMessage', [
                    'chat_id' => $chatId,
                    'parse_mode' => 'HTML',
                    'text' => $this->sanitize($html),
                    'disable_web_page_preview' => true,
                ]);

            if (! $response->successful()) {
                // Токен у лог не потрапляє: пишемо лише код і опис помилки від Telegram.
                Log::warning('Telegram: повідомлення не надіслано', [
                    'chat_id' => $chatId,
                    'status' => $response->status(),
                    'description' => $response->json('description'),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            // Повідомлення винятку може містити URL з токеном, тому логуємо лише клас помилки.
            Log::warning('Telegram: збій запиту', ['chat_id' => $chatId, 'error' => $e::class]);

            return false;
        }
    }

    /** Лишає лише дозволені Telegram теги (як у сайті, LeadNotifier). */
    public function sanitize(string $html): string
    {
        return preg_replace_callback('/<[^>]+>/', function (array $m): string {
            $tag = explode(' ', strtolower(trim($m[0], '</> ')))[0];

            return in_array($tag, self::ALLOWED_TAGS, true) ? $m[0] : '';
        }, $html);
    }
}
