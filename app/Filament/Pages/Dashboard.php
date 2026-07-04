<?php

namespace App\Filament\Pages;

use App\Services\Navigation\NavigationResolver;

/**
 * Replaces Filament's stock Dashboard: instead of greeting everyone with
 * the generic "Інфопанель", each user lands on the FIRST page of their
 * own (role-configured) sidebar — per explicit request. The per-role
 * sidebar already encodes "what matters most" for every role (see
 * NavigationResolver), so the first item is the natural home page.
 *
 * Behaviour details:
 * - If the first sidebar item IS this dashboard, the user (or the admin
 *   configuring that role's sidebar) put it there deliberately — no
 *   redirect, the dashboard renders as usual.
 * - If the sidebar is empty (role with nothing enabled yet), there is
 *   nowhere to go — dashboard renders as a safe fallback.
 */
class Dashboard extends \Filament\Pages\Dashboard
{
    public function mount(): void
    {
        $user = auth()->user();

        if (! $user) {
            return;
        }

        $first = NavigationResolver::resolveForSidebar($user->getActiveRoleName())
            ->flatMap(fn (array $group) => $group['items'])
            ->first();

        if ($first && filled($first['url'] ?? null) && $first['url'] !== static::getUrl()) {
            $this->redirect($first['url']);
        }
    }
}
