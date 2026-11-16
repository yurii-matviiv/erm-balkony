<?php

namespace App\Support;

use App\Models\User;

/**
 * Єдине місце, де вирішується "яка панель для цієї ролі".
 * Список ролей менеджерської панелі — config/panels.php.
 */
final class RolePanels
{
    public const ADMIN = 'admin';

    public const MANAGER = 'manager';

    public static function idForRole(?string $role): string
    {
        return in_array($role, config('panels.manager_roles', []), true)
            ? self::MANAGER
            : self::ADMIN;
    }

    public static function idForUser(?User $user): string
    {
        return self::idForRole($user?->getActiveRoleName());
    }

    /** Домашній URL панелі (без залежності від поточного контексту Filament). */
    public static function homeUrl(string $panelId): string
    {
        return url(\Filament\Facades\Filament::getPanel($panelId)->getPath());
    }
}
