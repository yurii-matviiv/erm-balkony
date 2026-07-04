<?php

namespace App\Services\Leads;

use App\Models\Lead;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;

/**
 * Read-only query behind the "Lead Export" page and its CSV download —
 * the table the marketing agency sees. Ported from the intermediate
 * "erm-balkony--Only-for-marketing-page" project, with one key change:
 * that project read the OLD CRM database directly (flat `leads` table),
 * while here everything comes from OUR OWN database, where the same data
 * is split across leads / lead_marketing_data / clients / orders (see
 * CLAUDE.md, "Модуль Заявки"). Reading our own DB means the page keeps
 * working after the old CRM is decommissioned.
 *
 * ONLY READ DATA — no insert/update/delete ever happens through this.
 */
class LeadExportQueryService
{
    public function getQuery(): Builder
    {
        return Lead::query()

            ->leftJoin(
                'clients',
                'clients.id',
                '=',
                'leads.client_id'
            )

            // 1:1 marketing snapshot (utm_*, gclid, ...) — filled by the
            // legacy sync now and by the future "site" module later.
            ->leftJoin(
                'lead_marketing_data',
                'lead_marketing_data.lead_id',
                '=',
                'leads.id'
            )

            // A lead CAN have several orders over time (the FK is not a
            // strict 1:1 — see Lead::latestOrder()). A plain join would
            // then duplicate the lead row in this table and double-count
            // it in the CSV, so join only the latest order per lead —
            // same "latest wins" rule as Lead::latestOrder().
            ->leftJoin('orders', function (JoinClause $join): void {
                $join->on('orders.lead_id', '=', 'leads.id')
                    ->whereRaw('orders.id = (select max(o2.id) from orders o2 where o2.lead_id = leads.id)');
            })

            ->select([

                'leads.id',

                'leads.created_at',

                'leads.source',

                'leads.stage',

                'leads.status as lead_status',

                'leads.lost_reason',

                'leads.comment',

                'lead_marketing_data.utm_source',

                'lead_marketing_data.utm_campaign',

                'lead_marketing_data.utm_medium',

                'lead_marketing_data.gclid',

                'clients.phone',

                'clients.email',

                'orders.total_price',

                'orders.success_date',

                'orders.status as order_status',
            ])

            // The old flat `name` column no longer exists (see CLAUDE.md,
            // "clients.name видалено") — assemble the display name from
            // the structured fields, same as Client::getFullNameAttribute().
            ->selectRaw(
                "trim(concat_ws(' ', clients.last_name, clients.first_name, clients.middle_name)) as client_name"
            );
    }

    /**
     * Shared between the Filament table filter and the CSV controller so
     * the downloaded file always matches what the agency sees on screen.
     */
    public function applyDateFilters(Builder $query, array $filters): Builder
    {
        $preset = $filters['preset'] ?? 'this_year';
        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;

        if ($preset === 'custom') {
            return $query
                ->when($dateFrom, fn (Builder $query) => $query->whereDate(
                    'leads.created_at', '>=', Carbon::parse($dateFrom)
                ))
                ->when($dateTo, fn (Builder $query) => $query->whereDate(
                    'leads.created_at', '<=', Carbon::parse($dateTo)
                ));
        }

        return match ($preset) {
            'today' => $query->whereDate('leads.created_at', today()),
            'yesterday' => $query->whereDate('leads.created_at', today()->subDay()),
            'this_month' => $query
                ->whereDate('leads.created_at', '>=', now()->startOfMonth())
                ->whereDate('leads.created_at', '<=', now()),
            'last_30_days' => $query
                ->whereDate('leads.created_at', '>=', now()->subDays(30))
                ->whereDate('leads.created_at', '<=', now()),
            default => $query // this_year
                ->whereDate('leads.created_at', '>=', now()->startOfYear())
                ->whereDate('leads.created_at', '<=', now()),
        };
    }

    /**
     * "Цільовий" for the agency = did this contact turn out to be a real
     * potential customer. Derived from the new stage/status pair instead
     * of the old 12-value status enum (mapping mirrors the semantics of
     * LeadsSyncMapper::STATUS_MAP): won → цільовий; lost → не цільовий
     * (all old disqualification reasons map to lost); still open but
     * moved past "new" → цільовий (someone qualified it enough to work
     * it); open and untouched → невідомо.
     */
    public static function targetLabel(?string $stage, ?string $status): string
    {
        return match (true) {
            $status === 'won' => 'цільовий',
            $status === 'lost' => 'не цільовий',
            $status === 'open' && $stage !== 'new' => 'цільовий',
            default => 'невідомо',
        };
    }

    public static function targetColor(?string $stage, ?string $status): string
    {
        return match (self::targetLabel($stage, $status)) {
            'цільовий' => 'success',
            'не цільовий' => 'danger',
            default => 'gray',
        };
    }

    /**
     * Coarse status group for the agency — same four buckets the old
     * project showed (новий / в роботі / продано / скасовано), now
     * computed from stage+status instead of the old mixed enum.
     */
    public static function statusGroupLabel(?string $stage, ?string $status): string
    {
        return match (true) {
            $status === 'won' => 'продано',
            $status === 'lost' => 'скасовано',
            $status === 'open' && $stage === 'new' => 'новий',
            $status === 'open' => 'в роботі',
            default => 'невідомо',
        };
    }

    public static function statusGroupColor(?string $stage, ?string $status): string
    {
        return match (self::statusGroupLabel($stage, $status)) {
            'продано' => 'success',
            'скасовано' => 'danger',
            'в роботі' => 'warning',
            'новий' => 'gray',
            default => 'gray',
        };
    }

    /**
     * Human label for `leads.source` — Lead::sourceOptions() only covers
     * the 3 manual values; synced historical leads also carry the
     * marketing/telephony channels from LeadsSyncMapper::SOURCE_MAP.
     */
    public static function sourceLabel(?string $source): string
    {
        return match ($source) {
            'call' => 'Дзвінок',
            'office_visit' => 'Візит в офіс',
            'referral' => 'Передали контакт',
            'site' => 'Заявка з сайту',
            'binotel_call' => 'Зворотній дзвінок',
            'binotel_chat' => 'Binotel chat',
            'fb_lead_ads' => 'Facebook lead',
            'fb_chat' => 'Facebook chat',
            default => '-',
        };
    }
}
