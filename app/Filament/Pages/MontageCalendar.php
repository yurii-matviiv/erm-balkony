<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

/**
 * Графік монтажів — full-page calendar showing orders with montage_date.
 *
 * Uses FullCalendar.js v6 (CDN) rendered in a Blade view. Events are
 * fetched from /admin/api/montage-events (see routes/web.php) as JSON,
 * so the calendar can lazy-load data per visible month without loading
 * all 2000+ orders upfront.
 *
 * Two display modes (toggled client-side, remembered in localStorage):
 *   - "По адресі"       (default) — each chip shows the order address
 *   - "По монтажниках"            — each chip shows the installer name
 *
 * Chip colors are deterministic per installer_id (same palette in PHP +
 * JS), so the same person always gets the same color across page loads.
 *
 * Role filter: Менеджер sees only own orders (manager_id = current user).
 * All other roles see the full schedule.
 */
class MontageCalendar extends Page
{
    protected string $view = 'filament.pages.montage-calendar';

    protected static ?string $navigationLabel = 'Графік монтажів';
    protected static ?string $title           = 'Графік монтажів';
    protected static \BackedEnum|string|null $navigationIcon  = 'heroicon-o-calendar-days';
    protected static \UnitEnum|string|null $navigationGroup = 'Замовлення';
    protected static ?int    $navigationSort  = 10;
}
