<?php

namespace App\Filament\Concerns;

/**
 * Default-DENY access for standalone Filament Pages.
 *
 * Why this exists: Filament protects Resources through model policies
 * (Shield), but a standalone Page is accessible to ANY authenticated
 * panel user by direct URL unless it defines canAccess() itself. That
 * hole let a Менеджер open "Аналітика рахунків" by URL even though the
 * page was never enabled in their sidebar — discovered by the user on
 * first real-account testing.
 *
 * Every page using this trait requires the Shield-style permission
 * `View:<ClassBasename>` — the exact permission SidebarPermissionSync
 * grants/revokes when the page is toggled in "Бокова панель". Together
 * they close the loop the user asked for: a page is reachable IF AND
 * ONLY IF it is enabled in the role's sidebar (super_admin bypasses all
 * checks via Shield's gate-before, as everywhere else).
 *
 * Do NOT add this to the Dashboard: it is the panel's home route and
 * must stay reachable for everyone (it immediately redirects to the
 * first sidebar item anyway).
 */
trait RequiresViewPermission
{
    public static function canAccess(): bool
    {
        return auth()->user()?->can('View:'.class_basename(static::class)) ?? false;
    }
}
