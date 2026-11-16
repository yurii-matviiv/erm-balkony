<?php

namespace App\Services\Navigation;

use App\Filament\Resources\LeadResource;
use Filament\Facades\Filament;
use Filament\Support\Contracts\HasLabel;

/**
 * Auto-discovers every navigation-visible Resource and Page registered in
 * the admin panel, with their CODE-DEFINED (default) label/group/sort —
 * i.e. exactly what the sidebar would show if nobody had customised
 * anything. This is the "what exists" half of the per-role sidebar
 * system; App\Services\Navigation\NavigationResolver is the "what a
 * specific role should see" half, which overlays NavigationSetting rows
 * on top of this.
 *
 * Built this way (introspecting the panel's registered classes) rather
 * than a hand-maintained list, per explicit request: a newly created
 * Resource/Page must "just appear" here without editing this class.
 */
class NavigationCatalog
{
    /**
     * @return array<int, array{key: string, label: string, group: string, sort: int, url: string}>
     */
    public static function discover(?string $panelId = null): array
    {
        // Each panel (admin / manager) has its own set of registered
        // resources and pages, so the catalog is always "for a panel". Default:
        // the panel being rendered right now. SidebarSettings asks for the
        // panel of the role it edits (see App\Support\RolePanels).
        $panelId ??= Filament::getCurrentPanel()?->getId() ?? 'admin';
        $panel = Filament::getPanel($panelId);

        $classes = [
            ...$panel->getResources(),
            ...$panel->getPages(),
        ];

        $items = [];

        foreach (array_unique($classes) as $class) {
            if (! method_exists($class, 'shouldRegisterNavigation')) {
                continue;
            }

            try {
                if (! $class::shouldRegisterNavigation()) {
                    continue;
                }

                $items[] = [
                    'key' => $class,
                    'label' => $class::getNavigationLabel(),
                    'group' => self::resolveGroupLabel($class::getNavigationGroup()),
                    'sort' => $class::getNavigationSort() ?? 0,
                    'url' => $class::getUrl(panel: $panelId),
                    'icon' => $class::getNavigationIcon(),
                ];
            } catch (\Throwable $e) {
                // A single misbehaving class (e.g. one that needs route
                // parameters this generic call can't guess) must not break
                // the whole catalog — just skip it.
                continue;
            }
        }

        // A manual shortcut, not backed by any Resource/Page class — see
        // AdminPanelProvider, which used to hardcode this directly into
        // the nav builder, invisible to (and unmanageable from) "Бокова
        // панель". Giving it a stable synthetic key here lets it flow
        // through the exact same group/order/active-toggle machinery as
        // everything else.
        if (in_array(LeadResource::class, $classes, true)) {
            $items[] = [
                'key' => 'quick-create-lead',
                'label' => 'Додати заявку',
                'group' => self::resolveGroupLabel(LeadResource::getNavigationGroup()),
                'sort' => -1,
                'url' => LeadResource::getUrl('index', ['create' => true], panel: $panelId),
                'icon' => 'heroicon-o-plus-circle',
            ];
        }

        usort($items, fn (array $a, array $b): int => $a['sort'] <=> $b['sort']);

        return $items;
    }

    private static function resolveGroupLabel(string|\UnitEnum|null $group): string
    {
        return match (true) {
            $group === null => 'Без групи',
            is_string($group) => $group,
            $group instanceof HasLabel => $group->getLabel() ?? $group->name,
            $group instanceof \BackedEnum => (string) $group->value,
            default => $group->name,
        };
    }
}
