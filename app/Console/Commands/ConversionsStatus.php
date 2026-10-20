<?php

namespace App\Console\Commands;

use App\Models\ConversionOutbox;
use Illuminate\Console\Command;

class ConversionsStatus extends Command
{
    protected $signature = 'conversions:status {--errors : показати останні помилки}';

    protected $description = 'Зведення черги офлайн-конверсій';

    public function handle(): int
    {
        $this->table(
            ['kind', 'status', 'skip_reason', 'кількість'],
            ConversionOutbox::query()
                ->selectRaw('kind, status, skip_reason, count(*) as c')
                ->groupBy('kind', 'status', 'skip_reason')
                ->orderBy('kind')->orderBy('status')
                ->get()->map(fn ($r) => [$r->kind, $r->status, $r->skip_reason ?? '', $r->c])->all(),
        );

        if ($this->option('errors')) {
            foreach (ConversionOutbox::whereNotNull('last_error')->latest('updated_at')->limit(10)->get() as $row) {
                $this->line("#{$row->id} lead {$row->lead_id} {$row->status}: {$row->last_error}");
            }
        }

        return self::SUCCESS;
    }
}
