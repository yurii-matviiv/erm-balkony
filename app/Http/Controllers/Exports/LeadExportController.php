<?php

namespace App\Http\Controllers\Exports;

use App\Http\Controllers\Controller;
use App\Services\Leads\LeadQueryService;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Http\Request;

class LeadExportController extends Controller
{
    /**
 * ---------------------------------------------------------
 * STACK / PROJECT STANDARD
 * ---------------------------------------------------------
 * Laravel 13.11.2
 * Livewire 3.8.0
 * Filament 4.11.5
 * Filament Shield 4.2.0
 * Spatie Permission 7.4.1
 * ---------------------------------------------------------
 * ACCESS:
 * Використовувати тільки Shield permissions.
 * НЕ використовувати hasRole().
 * ---------------------------------------------------------
 * FILAMENT 4 FILTERS API:
 * - HasFiltersForm
 * - Filament\Schemas\Schema
 * - filtersForm(Schema $schema): Schema
 *
 * НЕ використовувати:
 * - Filament\Forms\Form
 * - getFiltersFormSchema()
 * ---------------------------------------------------------
 */

    /**
     * ---------------------------------------------------------
     * EXPORT PAGE
     * ---------------------------------------------------------
     * Shows loading screen before CSV download
     * ---------------------------------------------------------
     */
    public function page(Request $request)
    {
       return view(
    'exports.lead-export-loading',
    [

        'preset' => $request->preset,

        'date_from' => $request->date_from,

        'date_to' => $request->date_to,

    ]
);
    }

    /**
     * ---------------------------------------------------------
     * EXPORT CSV
     * ---------------------------------------------------------
     */
    public function export(Request $request): StreamedResponse
    {
        $fileName = 'lead-export-' . now()->format('Y-m-d-H-i-s') . '.csv';

        return response()->streamDownload(function () use ($request) {

            $handle = fopen('php://output', 'w');

            /**
             * ---------------------------------------------------------
             * UTF-8 BOM
             * ---------------------------------------------------------
             */
            fwrite($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            /**
             * ---------------------------------------------------------
             * HEADER
             * ---------------------------------------------------------
             */
            fputcsv($handle, [

                'ID',
                'Created At',
                'Lead Status',
                'Source',
                'UTM Source',
                'UTM Campaign',
                'Name',
                'Phone',
                'Email',
                'Order Status',
                'Total Price',

            ]);

            /**
             * ---------------------------------------------------------
             * DATA
             * ---------------------------------------------------------
             */
            app(LeadQueryService::class)

    ->getQuery()
 
    /**
 * ---------------------------------------------------------
 * DATE FILTERS
 * ---------------------------------------------------------
 */
->when(

    $request->preset === 'today',

    fn ($query) => $query->whereDate(
        'leads.created_at',
        today()
    )

)

->when(

    $request->preset === 'yesterday',

    fn ($query) => $query->whereDate(
        'leads.created_at',
        today()->subDay()
    )

)

->when(

    $request->preset === 'this_month',

    fn ($query) => $query
        ->whereDate(
            'leads.created_at',
            '>=',
            now()->startOfMonth()
        )
        ->whereDate(
            'leads.created_at',
            '<=',
            now()
        )

)

->when(

    $request->preset === 'last_30_days',

    fn ($query) => $query
        ->whereDate(
            'leads.created_at',
            '>=',
            now()->subDays(30)
        )
        ->whereDate(
            'leads.created_at',
            '<=',
            now()
        )

)

->when(

    $request->preset === 'this_year',

    fn ($query) => $query
        ->whereDate(
            'leads.created_at',
            '>=',
            now()->startOfYear()
        )
        ->whereDate(
            'leads.created_at',
            '<=',
            now()
        )

)

->when(

    $request->preset === 'custom',

    fn ($query) => $query

        ->when(

            $request->date_from,

            fn ($query) => $query->whereDate(
                'leads.created_at',
                '>=',
                $request->date_from
            )

        )

        ->when(

            $request->date_to,

            fn ($query) => $query->whereDate(
                'leads.created_at',
                '<=',
                $request->date_to
            )

        )

)
    ->chunk(500, function ($rows) use ($handle) {

                    foreach ($rows as $row) {

                        fputcsv($handle, [

                            $row->id,
                            $row->created_at,
                            $row->lead_status,
                            $row->source,
                            $row->utm_source,
                            $row->utm_campaign,
                            $row->name,
                            $row->phone,
                            $row->email,
                            $row->order_status,
                            $row->total_price,

                        ]);
                    }
                });

            fclose($handle);

        }, $fileName);
    }
}