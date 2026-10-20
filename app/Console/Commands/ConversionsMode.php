<?php

namespace App\Console\Commands;

use App\Services\Conversions\ConversionSettings;
use Illuminate\Console\Command;

class ConversionsMode extends Command
{
    protected $signature = 'conversions:mode {mode? : off|validate|live} {--buffer= : хвилини буфера} {--test= : у режимі validate поставити в чергу N останніх заявок для перевірки}';

    protected $description = 'Показати/змінити режим офлайн-конверсій (off, validate = лише перевірка, live = бойовий)';

    public function handle(\App\Services\Conversions\ConversionQueue $queue): int
    {
        if ($this->option('buffer') !== null) {
            ConversionSettings::setBufferMinutes((int) $this->option('buffer'));
        }

        $mode = $this->argument('mode');

        if ($mode !== null) {
            if ($mode === 'live' && ! $this->confirm('Бойовий режим: конверсії підуть у Google Ads. Продовжити?')) {
                return self::FAILURE;
            }

            ConversionSettings::setMode($mode);
        }

        if ($this->option('test') !== null) {
            if (ConversionSettings::mode() !== 'validate') {
                $this->error('--test лише в режимі validate.');

                return self::FAILURE;
            }

            $this->info('Поставлено в чергу для перевірки: '.$queue->enqueueForTest((int) $this->option('test')));
        }

        $this->line('mode='.ConversionSettings::mode().', buffer='.ConversionSettings::bufferMinutes().' хв, baseline='.(ConversionSettings::baselineAt() ?? 'не виконано'));

        return self::SUCCESS;
    }
}
