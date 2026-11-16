<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class PushVapidKeys extends Command
{
    protected $signature = 'push:vapid-keys {--write : Append the keys to .env instead of printing them}';

    protected $description = 'Generate a VAPID key pair for Web Push (use --write to put it into .env)';

    public function handle(): int
    {
        if ($this->option('write')) {
            $path = base_path('.env');

            // Never overwrite existing keys: changing the public key invalidates every browser subscription.
            if (str_contains((string) file_get_contents($path), 'VAPID_PUBLIC_KEY=')) {
                $this->warn('VAPID keys are already in .env — nothing changed.');

                return self::SUCCESS;
            }

            $keys = VAPID::createVapidKeys();
            file_put_contents($path, "\nVAPID_PUBLIC_KEY={$keys['publicKey']}\nVAPID_PRIVATE_KEY={$keys['privateKey']}\n", FILE_APPEND);
            $this->info('VAPID keys written to .env.');

            return self::SUCCESS;
        }

        $keys = VAPID::createVapidKeys();
        $this->line('VAPID_PUBLIC_KEY=' . $keys['publicKey']);
        $this->line('VAPID_PRIVATE_KEY=' . $keys['privateKey']);

        return self::SUCCESS;
    }
}
