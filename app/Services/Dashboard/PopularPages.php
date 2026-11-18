<?php

namespace App\Services\Dashboard;

use App\Filament\Pages\Dashboard;
use App\Models\User;
use App\Services\Navigation\NavigationResolver;
use Illuminate\Support\Facades\DB;

/**
 * Найчастіші сторінки користувача для карток дашборда.
 *
 * Береться лише те, що є в боковому меню АКТИВНОЇ ролі користувача і до чого в нього
 * є доступ (тобто картка ніколи не веде на 403). Порядок: за кількістю відкриттів
 * (page_visits), при рівності — за останнім відкриттям. Якщо відвіданих сторінок
 * менше за ліміт, решту добирають із меню по порядку, без лічильника.
 */
class PopularPages
{
    public const LIMIT = 5;

    /**
     * @return list<array{rank:int,key:string,label:string,icon:string,url:string,visits:int,last_visited_at:?string}>
     */
    public static function forUser(User $user, int $limit = self::LIMIT): array
    {
        $menu = NavigationResolver::resolveForSidebar($user->getActiveRoleName())
            ->flatMap(fn (array $group) => $group['items'])
            ->filter(fn (array $item): bool => ($item['key'] ?? null) !== 'quick-create-lead'
                && ($item['key'] ?? null) !== Dashboard::class
                && filled($item['url'] ?? null)
                && self::canOpen($item['key'] ?? ''))
            ->values();

        $stats = DB::table('page_visits')->where('user_id', $user->id)->get()->keyBy('page_key');

        $sorted = $menu
            ->map(fn (array $item, int $position): array => $item + [
                'visits' => (int) ($stats[$item['key']]->visits ?? 0),
                'last' => $stats[$item['key']]->last_visited_at ?? null,
                'position' => $position,
            ])
            ->sort(fn (array $a, array $b): int => [$b['visits'], (string) $b['last'], $a['position']]
                <=> [$a['visits'], (string) $a['last'], $b['position']])
            ->take($limit)
            ->values();

        return $sorted->map(fn (array $item, int $i): array => [
            'rank' => $i + 1,
            'key' => $item['key'],
            'label' => (string) $item['label'],
            'icon' => is_string($item['icon'] ?? null) ? $item['icon'] : 'heroicon-o-squares-2x2',
            'url' => $item['url'],
            'visits' => $item['visits'],
            'last_visited_at' => $item['last'] ? \Illuminate\Support\Carbon::parse($item['last'])->format('d.m.Y H:i') : null,
        ])->all();
    }

    private static function canOpen(string $key): bool
    {
        if (! class_exists($key) || ! method_exists($key, 'canAccess')) {
            return false;
        }

        try {
            return (bool) $key::canAccess();
        } catch (\Throwable) {
            return false;
        }
    }
}
