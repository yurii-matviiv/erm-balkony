<?php

namespace App\Services\Leads;

use App\Models\Lead;
use Illuminate\Database\Eloquent\Builder;

class LeadQueryService
{
    /**
     * ---------------------------------------------------------
     * GET QUERY
     * ---------------------------------------------------------
     * ONLY READ DATA
     * NO INSERT / UPDATE / DELETE
     * ---------------------------------------------------------
     */
    public function getQuery(): Builder
    {
        return Lead::query()

            ->from('leads')

            ->leftJoin(
                'clients',
                'clients.id',
                '=',
                'leads.client_id'
            )

            ->leftJoin(
                'orders',
                'orders.lead_id',
                '=',
                'leads.id'
            )

            ->select([

                'leads.id',

                'leads.source',

                'leads.created_at',

                'leads.status as lead_status',

                'leads.comment',

                'leads.comment_callback',

                'leads.utm_source',

                'leads.utm_campaign',

                'leads.utm_medium',

                'leads.gclid',

                'clients.name',

                'clients.phone',

                'clients.email',

                'orders.total_price',

                'orders.success_date',

                'orders.status as order_status',
            ]);
    }
}