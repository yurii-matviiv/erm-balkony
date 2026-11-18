<?php

namespace App\Http\Middleware;

use App\Filament\Pages\Dashboard;
use App\Services\Impersonation\Impersonator;
use Closure;
use Filament\Pages\Page;
use Filament\Resources\Pages\Page as ResourcePage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Рахує відкриття сторінок меню користувачем (таблиця page_visits).
 *
 * Рахуємо лише справжні завантаження сторінки: GET, успішна відповідь, не Livewire
 * і не JSON. Сторінки ресурсу (список, картка, редагування) йдуть у лічильник самого
 * ресурсу, бо в меню це один пункт. Дашборд не рахуємо. Коли адмін працює під чужим
 * акаунтом («Увійти як»), візити не записуються, щоб не псувати статистику того,
 * в кого він зайшов. Збій обліку ніколи не ламає сторінку.
 */
class TrackPageVisit
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            $this->track($request, $response);
        } catch (\Throwable $e) {
            report($e);
        }

        return $response;
    }

    private function track(Request $request, Response $response): void
    {
        $user = $request->user();

        if (
            ! $user
            || ! $request->isMethod('GET')
            || $request->expectsJson()
            || $request->headers->has('X-Livewire')
            || $response->getStatusCode() !== 200
            || Impersonator::active()
        ) {
            return;
        }

        $key = self::pageKey($request->route()?->getActionName());

        if ($key === null) {
            return;
        }

        DB::table('page_visits')->upsert(
            [['user_id' => $user->id, 'page_key' => $key, 'visits' => 1, 'last_visited_at' => now()]],
            ['user_id', 'page_key'],
            ['visits' => DB::raw('visits + 1'), 'last_visited_at' => now()],
        );
    }

    /** Ключ пункту меню (FQCN Resource/Page) для дії маршруту, або null, якщо рахувати не треба. */
    public static function pageKey(?string $action): ?string
    {
        $class = $action ? explode('@', $action)[0] : null;

        if (! $class || ! class_exists($class)) {
            return null;
        }

        $key = match (true) {
            is_subclass_of($class, ResourcePage::class) => $class::getResource(),
            is_subclass_of($class, Page::class) => $class,
            default => null,
        };

        if ($key === null || $key === Dashboard::class || ! method_exists($key, 'shouldRegisterNavigation')) {
            return null;
        }

        return $key::shouldRegisterNavigation() ? $key : null;
    }
}
