<?php

namespace App\Services\Navigation;

use App\Models\NavigationLabel;
use App\Models\NavigationSetting;
use Illuminate\Support\Collection;

/**
 * Merges NavigationCatalog (what exists, with code defaults) with
 * NavigationSetting rows (per-role overrides) — see that table's
 * migration docblock. A role with no overrides at all gets exactly the
 * code-default sidebar; only items someone has explicitly customised in
 * "Бокова панель" differ.
 */
class NavigationResolver
{
    /**
     * Roles that see new navigation items by default (without any explicit
     * NavigationSetting row). All other roles default to hidden — items
     * must be explicitly enabled via the "Бокова панель" settings page.
     */
    private const ADMIN_ROLES = ['super_admin', 'founder'];

    /**
     * Full merged list for the settings page editor — includes INACTIVE
     * items too (the editor needs to show and toggle them), each tagged
     * with whether it's overridden or still using the code default.
     *
     * @return Collection<int, array{key:string,label:string,group:string,group_sort:int,item_sort:int,is_active:bool}>
     */
    public static function resolveForEditor(string $role): Collection
    {
        $catalog = collect(NavigationCatalog::discover())->keyBy('key');

        $settings = NavigationSetting::where('role', $role)->get()->keyBy('item_key');
        $labels = NavigationLabel::pluck('label', 'item_key');

        // Give every catalog group a stable default group_sort (the
        // lowest item sort seen in that group), so groups with no
        // per-role override yet still come out in the same order the
        // live sidebar would show them in.
        $defaultGroupSort = $catalog
            ->groupBy('group')
            ->map(fn (Collection $items) => $items->min('sort'));

        // Admin roles see everything by default; all other roles see
        // nothing until the admin explicitly enables items in "Бокова панель".
        $defaultActive = in_array($role, self::ADMIN_ROLES, true);

        return $catalog->map(function (array $item) use ($settings, $defaultGroupSort, $labels, $defaultActive): array {
            $setting = $settings->get($item['key']);

            return [
                'key' => $item['key'],
                'label' => $labels->get($item['key']) ?? $item['label'],
                'url' => $item['url'],
                'icon' => $item['icon'],
                'group' => $setting->group_label ?? $item['group'],
                'group_sort' => $setting->group_sort ?? $defaultGroupSort->get($item['group'], 0),
                'item_sort' => $setting->item_sort ?? $item['sort'],
                'is_active' => $setting?->is_active ?? $defaultActive,
            ];
        })->values();
    }

    /**
     * What the live sidebar should actually render for this role —
     * active items only, grouped and ordered. $role may be null (no
     * roles at all) — falls back to the plain code-default catalog so
     * the panel never ends up with an empty sidebar.
     *
     * @return Collection<int, array{label:string, sort:int, items: Collection}>
     */
    public static function resolveForSidebar(?string $role): Collection
    {
        if ($role === null) {
            // No role at all (shouldn't normally happen) — fall back to
            // plain code defaults, reshaped to match resolveForEditor()'s
            // shape so the rest of this method can stay branch-agnostic.
            $labels = NavigationLabel::pluck('label', 'item_key');

            $items = collect(NavigationCatalog::discover())->map(fn (array $item): array => [
                'key' => $item['key'],
                'label' => $labels->get($item['key']) ?? $item['label'],
                'url' => $item['url'],
                'icon' => $item['icon'],
                'group' => $item['group'],
                'group_sort' => $item['sort'],
                'item_sort' => $item['sort'],
                'is_active' => true,
            ]);
        } else {
            $items = self::resolveForEditor($role)->where('is_active', true);
        }

        return $items
            ->groupBy('group')
            ->map(fn (Collection $items, string $group): array => [
                'label' => $group,
                'sort' => $items->first()['group_sort'] ?? 0,
                'items' => $items->sortBy('item_sort')->values(),
            ])
            ->sortBy('sort')
            ->values();
    }
}
