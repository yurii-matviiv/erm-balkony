<?php

namespace App\Http\Controllers\Exports;

use App\Http\Controllers\Controller;
use App\Services\Leads\LeadQueryService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeadExportController extends Controller
{
    /**
     * ---------------------------------------------------------
     * EXPORT CSV
     * ---------------------------------------------------------
     */
    public function export(): StreamedResponse
    {
        $fileName = 'lead-export-' . now()->format('Y-m-d-H-i-s') . '.csv';

        return response()->streamDownload(function () {

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