<?php

namespace App\Http\Middleware;

use App\Support\RolePanels;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Якщо залогінений користувач відкрив "не свою" панель (менеджер — /admin,
 * адмін у ролі менеджера — /admin тощо), відправляє його у панель, що відповідає
 * його активній ролі. Так вхід з будь-якої адреси (/admin/login, /manager/login)
 * веде в правильне місце, а canAccessPanel() не дає оманливого
 * "credentials do not match" через панель.
 *
 * Тільки для звичайних GET-переходів: Livewire-запити (оновлення компонентів,
 * завантаження файлів) та JSON не чіпаємо — інакше redirect зламав би їх.
 */
class RedirectToRolePanel
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $panel = Filament::getCurrentPanel();

        if (
            $user
            && $panel
            && $request->isMethod('GET')
            && ! $request->expectsJson()
            && ! $request->headers->has('X-Livewire')
        ) {
            $target = RolePanels::idForUser($user);

            if ($target !== $panel->getId()) {
                return redirect()->to(RolePanels::homeUrl($target));
            }
        }

        return $next($request);
    }
}
