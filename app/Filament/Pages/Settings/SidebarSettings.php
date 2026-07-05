<?php

namespace App\Filament\Pages\Settings;

use App\Models\NavigationLabel;
use App\Models\NavigationSetting;
use App\Services\Navigation\NavigationResolver;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

/**
 * "Бокова панель" — per-role sidebar editor. Reachable only via
 * "Інтерфейс" (see InterfaceSettings) — not in the main nav itself
 * ($shouldRegisterNavigation = false), so it doesn't show up twice.
 *
 * Deliberately plain Livewire/Blade, NOT a Filament Table — the first
 * version used `HasTable`/`InteractsWithTable`, but a flat table repeating
 * the group name on every row was confusing, and it wasn't obvious
 * whether clicking the reorder actions did anything. Rendering the
 * groups/items as actual nested sections (mirroring the real sidebar's
 * shape) and using plain `wire:click` methods makes both problems go
 * away: the structure IS the sidebar's structure, and every click
 * re-renders this component immediately, so changes are visible at once.
 *
 * Two different save behaviours, per explicit request:
 * - Reordering (group/item arrows) and renaming save immediately.
 * - Turning items on/off is staged in $pendingActive (just a visual
 *   toggle, not yet persisted) until "Застосувати зміни" is pressed —
 *   toggling used to instantly re-sort the list (inactive items jump to
 *   the bottom right away), which was disorientating when flipping
 *   several switches in a row.
 */
class SidebarSettings extends Page
{
    use \App\Filament\Concerns\RequiresViewPermission;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bars-3';

    protected static string|\UnitEnum|null $navigationGroup = 'Налаштування';

    protected static ?string $navigationLabel = 'Бокова панель';

    protected static ?string $title = 'Бокова панель';

    protected static ?string $slug = 'settings/sidebar';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.settings.sidebar-settings';

    public string $role = '';

    /**
     * Staged is_active changes, keyed by item_key — NOT written to the
     * database until applyActiveChanges() runs. See class docblock.
     *
     * @var array<string, bool>
     */
    public array $pendingActive = [];

    public function mount(): void
    {
        $this->role = auth()->user()?->getActiveRoleName()
            ?? Role::query()->orderBy('name')->value('name')
            ?? '';
    }

    public function updatedRole(): void
    {
        // Pending toggles belong to whichever role we were just looking
        // at — switching role without applying them first should discard
        // them, not silently carry them over to a different role.
        $this->pendingActive = [];
    }

    /**
     * @return array<string, string>
     */
    public function getRoleOptions(): array
    {
        $roles = Role::query()->orderBy('name')->pluck('name', 'name')->all();
        $activeRole = auth()->user()?->getActiveRoleName();

        if ($activeRole && isset($roles[$activeRole])) {
            $roles = [$activeRole => $roles[$activeRole]] + $roles;
        }

        return $roles;
    }

    /**
     * All items for the current role, with staged-but-not-yet-applied
     * toggles (see $pendingActive) reflected in `is_active` immediately —
     * without touching the persisted sort order. Re-evaluated fresh on
     * every render, which is what makes every action below feel instant.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function resolveItemsWithPending(): Collection
    {
        return NavigationResolver::resolveForEditor($this->role)
            ->map(fn (array $item): array => [
                ...$item,
                'is_active' => $this->pendingActive[$item['key']] ?? $item['is_active'],
            ]);
    }

    /**
     * ONLY active items, grouped/ordered exactly like the real sidebar —
     * per explicit request, this must be a 1:1 match: no inactive items
     * mixed in, so this section alone tells you exactly what that role
     * currently sees.
     *
     * @return Collection<int, array{label: string, items: Collection}>
     */
    public function getActiveGroupedItems(): Collection
    {
        return $this->resolveItemsWithPending()
            ->where('is_active', true)
            ->groupBy('group')
            ->map(fn (Collection $groupItems, string $group): array => [
                'label' => $group,
                'group_sort' => $groupItems->first()['group_sort'],
                'items' => $groupItems->sortBy('item_sort')->values(),
            ])
            ->sortBy('group_sort')
            ->values();
    }

    /**
     * Everything currently switched off, in one separate list at the
     * bottom — so re-enabling something doesn't mean hunting for it
     * inside a group full of active items. Still labelled with its group
     * (just as plain text, not a full group box) so it's clear where it
     * would land once switched back on.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getInactiveItems(): Collection
    {
        return $this->resolveItemsWithPending()
            ->where('is_active', false)
            ->sortBy([
                ['group_sort', 'asc'],
                ['item_sort', 'asc'],
            ])
            ->values();
    }

    public function hasPendingChanges(): bool
    {
        return $this->pendingActive !== [];
    }

    public function applyActiveChanges(): void
    {
        foreach ($this->pendingActive as $itemKey => $isActive) {
            $this->saveSetting($itemKey, ['is_active' => $isActive]);

            // Visibility and access move together, per explicit request:
            // enabling an item also grants the role VIEW permission on it,
            // disabling revokes it — see SidebarPermissionSync docblock
            // (action permissions still managed manually on "Ролі").
            \App\Services\Navigation\SidebarPermissionSync::apply($this->role, $itemKey, $isActive);
        }

        $this->pendingActive = [];
    }

    public function toggleActive(string $itemKey): void
    {
        $current = NavigationResolver::resolveForEditor($this->role)->firstWhere('key', $itemKey);
        $currentlyActive = $this->pendingActive[$itemKey] ?? $current['is_active'] ?? true;

        $this->pendingActive[$itemKey] = ! $currentlyActive;
    }

    /**
     * Swaps this item with its neighbour above/below within the same
     * group, then re-numbers every sibling sequentially (0, 1, 2, ...) —
     * not a raw value swap, because most items share the same code
     * default sort (0), which would make a naive swap a no-op.
     */
    public function moveItem(string $itemKey, int $direction): void
    {
        $editor = NavigationResolver::resolveForEditor($this->role);
        $current = $editor->firstWhere('key', $itemKey);

        if (! $current) {
            return;
        }

        $siblings = $editor->where('group', $current['group'])->sortBy('item_sort')->values();
        $index = $siblings->search(fn (array $item) => $item['key'] === $itemKey);
        $swapIndex = $index + $direction;

        if ($index === false || $swapIndex < 0 || $swapIndex >= $siblings->count()) {
            return;
        }

        $ordered = $siblings->all();
        [$ordered[$index], $ordered[$swapIndex]] = [$ordered[$swapIndex], $ordered[$index]];

        foreach ($ordered as $sortValue => $item) {
            $this->saveSetting($item['key'], ['item_sort' => $sortValue]);
        }
    }

    /**
     * Moves the WHOLE GROUP this item belongs to up/down relative to the
     * other groups — every item belonging to either of the two swapped
     * groups gets its group_sort re-numbered, same reasoning as
     * moveItem(). Takes any item key from the group rather than the group
     * name itself, since the Blade view only has items to reference.
     */
    public function moveGroup(string $anyItemKeyInGroup, int $direction): void
    {
        $editor = NavigationResolver::resolveForEditor($this->role);
        $current = $editor->firstWhere('key', $anyItemKeyInGroup);

        if (! $current) {
            return;
        }

        $groupOrder = $editor
            ->groupBy('group')
            ->map(fn (Collection $items) => $items->first()['group_sort'])
            ->sortBy(fn ($sort) => $sort)
            ->keys()
            ->values();

        $index = $groupOrder->search($current['group']);
        $swapIndex = $index + $direction;

        if ($index === false || $swapIndex < 0 || $swapIndex >= $groupOrder->count()) {
            return;
        }

        $ordered = $groupOrder->all();
        [$ordered[$index], $ordered[$swapIndex]] = [$ordered[$swapIndex], $ordered[$index]];

        foreach ($ordered as $sortValue => $groupName) {
            $editor->where('group', $groupName)->each(
                fn (array $item) => $this->saveSetting($item['key'], ['group_sort' => $sortValue]),
            );
        }
    }

    /**
     * Renames the group, for this role only (group_label is a per-role
     * column on navigation_settings — unlike the item label override
     * below, which is global). Applies to every item currently in that
     * group, since the group's identity is shared across all of them.
     */
    public function renameGroup(string $anyItemKeyInGroup, string $newLabel): void
    {
        if (blank($newLabel)) {
            return;
        }

        $editor = NavigationResolver::resolveForEditor($this->role);
        $current = $editor->firstWhere('key', $anyItemKeyInGroup);

        if (! $current) {
            return;
        }

        $editor->where('group', $current['group'])->each(
            fn (array $item) => $this->saveSetting($item['key'], ['group_label' => $newLabel]),
        );
    }

    private function saveSetting(string $itemKey, array $values): void
    {
        NavigationSetting::updateOrCreate(
            ['role' => $this->role, 'item_key' => $itemKey],
            $values,
        );
    }

    /**
     * Global (not per-role) label override — see create_navigation_labels_table.
     * Clearing the field back to empty removes the 