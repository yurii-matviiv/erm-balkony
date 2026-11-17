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
        // Кваліфікована заявка — як у ConversionPlanner::planQualified: етап не «Новий», результат не «Втрачено»
        // і це не рекламація (тип послуги «Рекламація» або старий статус reklamatsiya_amtech).
        // Рішення лише за stage/status — і для лідів зі старої CRM, і для нових (sync:legacy вже перевів старі статуси).
        $complaint = (string) config('conversions.complaint_service_type', 'Рекламація');
        $leadCond = "l.status <> 'lost' AND l.stage <> 'new'"
            ." AND COALESCE(l.legacy_status, '') <> 'reklamatsiya_amtech'" // LEGACY-CLEANUP: прибрати після повного переходу
            .' AND NOT EXISTS (SELECT 1 FROM lead_lead_service_type x JOIN lead_service_types t ON t.id = x.lead_service_type_id'
            .' WHERE x.lead_id = l.id AND t.name = ?)';

        $toCheck = fn () => DB::table('clients as c')->where(fn ($q) => $q->whereNull('c.ad_status')->orWhere('c.ad_status', 'unqualified'));

        $qualifiedSql = "EXISTS (SELECT 1 FROM leads l WHERE l.client_id = c.id AND ($leadCond))"
            .' OR EXISTS (SELECT 1 FROM orders o WHERE o.client_id = c.id)';

        $q = $toCheck()->whereRaw("($qualifiedSql)", [$complaint]);
        $newQualified = (clone $q)->count();

        $this->line('до перевірки: '.$toCheck()->count().'; стануть qualified: '.$newQualified);

        if ($this->option('dry')) {
            return self::SUCCESS;
        }

        $now = now();

        DB::table('clients as c')
            ->where(fn ($q) => $q->whereNull('c.ad_status')->orWhere('c.ad_status', 'unqualified'))
            ->whereRaw("($qualifiedSql)", [$complaint])
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
