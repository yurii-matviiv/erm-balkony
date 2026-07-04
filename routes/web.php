<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect('/admin')
        : redirect('/admin/login');
});

// ── Developer tools — super_admin only ────────────────────────────────────
// Clears all caches that can cause stale UI during development.
// Protected by auth + role check; not exposed in production without login.
Route::post('/dev/clear-cache', function () {
    if (! auth()->check() || auth()->user()->getActiveRoleName() !== 'super_admin') {
        abort(403);
    }

    $results = [];

    foreach ([
        'cache:clear'  => 'Кеш додатку',
        'view:clear'   => 'Шаблони (Blade)',
        'config:clear' => 'Конфігурація',
        'route:clear'  => 'Маршрути',
        'event:clear'  => 'Події',
    ] as $command => $label) {
        try {
            Artisan::call($command);
            $results[] = "✓ {$label}";
        } catch (\Throwable $e) {
            $results[] = "✗ {$label}: " . $e->getMessage();
        }
    }

    return response()->json([
        'ok'      => true,
        'cleared' => $results,
    ]);
})->middleware(['web', 'auth'])->name('dev.clear-cache');

// ── Lead export for the marketing agency ─────────────────────────────────
// Streamed CSV download behind the "Експорт лідів" Filament page. Both
// endpoints re-check the `View:LeadExport` permission inside the
// controller (NOT via `can:` middleware — the ":" in the permission name
// collides with the middleware parameter separator). `auth` middleware
// here only provides the redirect-to-login behaviour for guests.
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/lead-export', [\App\Http\Controllers\Exports\LeadExportController::class, 'page'])
        ->name('lead-export.page');

    Route::get('/lead-export/csv', [\App\Http\Controllers\Exports\LeadExportController::class, 'export'])
        ->name('lead-export.csv');
});

// ── Montage calendar events API ───────────────────────────────────────────
// Returns orders with montage_date as FullCalendar-compatible JSON events.
// Менеджер sees only their own orders; all other roles see everyone's.
Route::get('/admin/api/montage-events', function () {
    $user = auth()->user();
    $role = $user?->getActiveRoleName();

    $start = request('start'); // ISO date from FullCalendar
    $end   = request('end');

    // Deterministic colors per installer — same palette used client-side
    $colors = [
        '#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6',
        '#ec4899', '#06b6d4', '#f97316', '#6366f1', '#14b8a6',
        '#f43f5e', '#a855f7', '#0ea5e9', '#84cc16', '#eab308',
    ];
    // Colors where white text is unreadable — use dark text instead
    $needsDarkText = ['#f59e0b', '#84cc16', '#eab308'];

    $stageLabels = \App\Models\Order::stageOptions();

    $query = \App\Models\Order::query()
        ->with(['client', 'installer', 'manager', 'surveyor'])
        ->whereNotNull('montage_date')
        ->where('status', '!=', 'cancelled');

    // Role-based filter: Менеджер sees only own orders
    if ($role === 'Менеджер') {
        $query->where('manager_id', $user->id);
    }

    if ($start) {
        $query->where('montage_date', '>=', substr($start, 0, 10));
    }
    if ($end) {
        $query->where('montage_date', '<=', substr($end, 0, 10));
    }

    $events = $query->get()->flatMap(function (\App\Models\Order $order) use ($colors, $needsDarkText, $stageLabels) {
        $installerId  = (int) ($order->installer_id ?? 0);
        $bgColor      = $installerId ? $colors[$installerId % count($colors)] : '#94a3b8';
        $textColor    = in_array($bgColor, $needsDarkText) ? '#1f2937' : '#ffffff';

        $baseProps = [
            'orderId'       => $order->id,
            'address'       => $order->address ?? '—',
            'clientName'    => $order->client?->full_name ?? '—',
            'clientPhone'   => $order->client?->phone ?? '',
            'installerName' => $order->installer?->name ?? 'Без монтажника',
            'surveyorName'  => $order->surveyor?->name ?? '—',
            'managerName'   => $order->manager?->name ?? '—',
            'installerId'   => $order->installer_id,
            'totalPrice'    => $order->total_price
                ? number_format((float) $order->total_price, 0, '', ' ') . ' грн'
                : '—',
            'stageLabel'    => $stageLabels[$order->stage] ?? $order->stage,
            'editUrl'       => url('/admin/orders/' . $order->id . '/edit'),
        ];

        $rows = [];

        // Primary montage_date
        $dateFormatted = \Carbon\Carbon::parse($order->montage_date)->format('Y-m-d');
        $rows[] = [
            'id'              => 'order-' . $order->id,
            'title'           => $order->address ?? 'Замовлення #' . $order->id,
            'start'           => $dateFormatted,
            'backgroundColor' => $bgColor,
            'borderColor'     => $bgColor,
            'textColor'       => $textColor,
            'extendedProps'   => array_merge($baseProps, [
                'montageDate' => \Carbon\Carbon::parse($order->montage_date)->format('d.m.Y'),
            ]),
        ];

        // Extra montage dates (montage_date_2, _3, _4)
        foreach ([2, 3, 4] as $n) {
            $field = "montage_date_{$n}";
            if (! empty($order->$field)) {
                $extraDate = \Carbon\Carbon::parse($order->$field)->format('Y-m-d');
                $rows[] = [
                    'id'              => "order-{$order->id}-{$n}",
                    'title'           => $order->address ?? 'Замовлення #' . $order->id,
                    'start'           => $extraDate,
                    'backgroundColor' => $bgColor,
                    'borderColor'     => $bgColor,
                    'textColor'       => $textColor,
                    'extendedProps'   => array_merge($baseProps, [
                        'montageDate' => \Carbon\Carbon::parse($order->$field)->format('d.m.Y'),
                    ]),
                ];
            }
        }

        return $rows;
    });

    return response()->json(array_values($events->all()));
})->middleware(['web', 'auth'])->name('admin.api.montage-events');
