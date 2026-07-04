<?php

namespace App\Http\Controllers\Exports;

use App\Http\Controllers\Controller;
use App\Services\Leads\LeadExportQueryService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV download behind the "Експорт лідів" Filament page — ported from the
 * intermediate "erm-balkony--Only-for-marketing-page" project. Two-step
 * flow kept from there: page() shows a tiny self-closing loading screen,
 * which then triggers export() — a streamed download can take a few
 * seconds on 14k+ leads, and without the intermediate page the browser
 * tab just looks frozen.
 *
 * IMPORTANT fix over the original: there these routes were registered
 * with NO auth at all — anyone with the URL could download the full lead
 * base. Here both endpoints require the same `View:LeadExport` Shield
 * permission as the page itself (checked in authorize(), not middleware,
 * because the permission name contains ":" which the `can:` middleware
 * parameter syntax would split).
 */
class LeadExportController extends Controller
{
    private function authorizeExport(): void
    {
        abort_unless(
            auth()->check() && auth()->user()->can('View:LeadExport'),
            403
        );
    }

    public function page(Request $request)
    {
        $this->authorizeExport();

        return view('exports.lead-export-loading', [
            'preset' => $request->preset,
            'date_from' => $request->date_from,
            'date_to' => $request->date_to,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorizeExport();

        $fileName = 'lead-export-'.now()->format('Y-m-d-H-i-s').'.csv';

        return response()->streamDownload(function () use ($request): void {

            $handle = fopen('php://output', 'w');

            // UTF-8 BOM — without it Excel opens Cyrillic as mojibake.
            fwrite($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, [
                'ID',
                'Created At',
                'Source',
                'UTM Source',
                'UTM Campaign',
                'UTM Medium',
                'Target',
                'Status',
                'Name',
                'Phone',
                'Email',
                'Order Status',
                'Total Price',
                'GCLID',
            ]);

            $service = app(LeadExportQueryService::class);

            $query = $service->applyDateFilters($service->getQuery(), [
                'preset' => $request->preset,
                'date_from' => $request->date_from,
                'date_to' => $request->date_to,
            ]);

            // chunkById would break on the joined query (ambiguous id
            // ordering) — plain chunk over the already-ordered query,
            // same as the original implementation.
            $query
                ->orderBy('leads.id')
                ->chunk(500, function ($rows) use ($handle): void {
                    foreach ($rows as $row) {
                        fputcsv($handle, [
                            $row->id,
                            $row->created_at,
                            LeadExportQueryService::sourceLabel($row->source),
                            $row->utm_source,
                            $row->utm_campaign,
                            $row->utm_medium,
                            LeadExportQueryService::targetLabel($row->stage, $row->lead_status),
                            LeadExportQueryService::statusGroupLabel($row->stage, $row->lead_status),
                            $row->client_name,
                            $row->phone,
                            $row->email,
                            $row->order_status,
                            $row->total_price,
                            $row->gclid,
                        ]);
                    }
                });

            fclose($handle);

        }, $fileName);
    }
}
