<?php

namespace App\Console\Commands;

use App\Services\Navigation\NavigationResolver;
use App\Services\Navigation\SidebarPermissionSync;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

/**
 * One-shot alignment of VIEW permissions with the per-role sidebar
 * configuration. Needed because the sidebar editor only syncs permissions
 * for toggles made AFTER the coupling was introduced (see
 * SidebarPermissionSync) — roles configured before that (e.g. Менеджер)
 * had visible menu items but zero permissions, giving 403 on click.
 *
 * Safe to re-run at any time: it simply re-applies "visible = viewable"
 * for every role and every catalog item.
 */
class SyncSidebarPermissions extends Command
{
    protected $signature = 'sidebar:sync-permissions';

    protected $description = 'Grant/revoke view permissions so they match each role\'s sidebar visibility';

    public function handle(): int
    {
        foreach (Role::where('guard_name', 'web')->get() as $role) {
            $items = NavigationResolver::resolveForEditor($role->name);

            foreach ($items as $item) {
                SidebarPermissionSync::apply($role->name, $item['key'], (bool) $item['is_active']);
            }

            $this->info($role->name.': '.$items->where('is_active', true)->count().' активних пунктів синхронізовано');
        }

        return self::SUCCESS;
    }
}
