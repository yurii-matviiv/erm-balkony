<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AudienceClassify extends Command
{
    protected $signature = 'audience:classify {--dry : лише порахувати}';

    protected $description = 'Позначити клієнтів clients.ad_status: qualified (є кваліфікована заявка або замовлення) / unqualified. qualified не знімається; перевіряються лише нові та unqualified.';

    public function handle(): int
    {
        $junk = (array) config('conversions.junk_statuses', []);
        $in = implode(',', array_fill(0, max(1, count($junk)), '?'));

        // Кваліфікована заявка — як у ConversionPlanner::planQualified
        $leadCond = "(l.legacy_status IS NOT NULL AND l.legacy_status <> 'new' AND l.legacy_status NOT IN ($in))"
            ." OR (l.legacy_status IS NULL AND l.status <> 'lost' AND l.stage <> 'new')";

        $toCheck = fn () => DB::table('clients as c')->where(fn ($q) => $q->whereNull('c.ad_status')->orWhere('c.ad_status', 'unqualified'));

        $qualifiedSql = "EXISTS (SELECT 1 FROM leads l WHERE l.client_id = c.id AND ($leadCond))"
            .' OR EXISTS (SELECT 1 FROM orders o WHERE o.client_id = c.id)';

        $q = $toCheck()->whereRaw("($qualifiedSql)", $junk);
        $newQualified = (clone $q)->count();

        $this->line('до перевірки: '.$toCheck()->count().'; стануть qualified: '.$newQualified);

        if ($this->option('dry')) {
            return self::SUCCESS;
        }

        $now = now();

        DB::table('clients as c')
            ->where(fn ($q) => $q->whereNull('c.ad_status')->orWhere('c.ad_status', 'unqualified'))
            ->whereRaw("($qualifiedSql)", $junk)
            ->update(['c.ad_status' => 'qualified', 'c.ad_status_checked_at' => $now]);

        DB::table('clients')
            ->whereNull('ad_status')
            ->update(['ad_status' => 'unqualified', 'ad_status_checked_at' => $now]);

        DB::table('clients')->where('ad_status', 'unqualified')->update(['ad_status_checked_at' => $now]);

        $this->line(json_encode([
            'qualified' => DB::table('clients')->where('ad_status', 'qualified')->count(),
            'unqualified' => DB::table('clients')->where('ad_status', 'unqualified')->count(),
        ]));

        return self::SUCCESS;
    }
}
