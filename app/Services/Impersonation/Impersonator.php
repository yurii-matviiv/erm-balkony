<?php

namespace App\Services\Impersonation;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * «Увійти як користувач»: super_admin переходить в акаунт іншого
 * користувача без пароля, а потім одним кліком повертається назад.
 *
 * Права — РЕАЛЬНА роль (hasRole), не перемикач active_role: це справжня
 * влада над чужими акаунтами. Id того, хто увійшов, лежить у сесії
 * (KEY) — за ним працюють банер «Повернутись» і кнопка виходу.
 */
final class Impersonator
{
    public const KEY = 'impersonator_id';

    public static function active(): bool
    {
        return session()->has(self::KEY);
    }

    public static function impersonator(): ?User
    {
        $id = session(self::KEY);

        return $id ? User::find($id) : null;
    }

    public static function canImpersonate(?User $actor, User $target): bool
    {
        return $actor !== null
            && ! self::active()
            && $actor->hasRole('super_admin')
            && $actor->id !== $target->id
            // Не входимо в інших super_admin і в акаунти, які однаково не пустить у панель.
            && ! $target->hasRole('super_admin')
            && (bool) $target->is_active
            && $target->roles()->exists();
    }

    public static function start(User $actor, User $target): void
    {
        abort_unless(self::canImpersonate($actor, $target), 403);

        Log::info('impersonation.start', ['actor_id' => $actor->id, 'target_id' => $target->id]);

        Auth::guard('web')->login($target);
        session()->regenerate();
        session()->put(self::KEY, $actor->id);
        // Хеш пароля попереднього користувача лишився б у сесії, і AuthenticateSession
        // розлогінив би нового: прибираємо, на наступному запиті він запишеться наново.
        session()->forget('password_hash_web');
    }

    /** Повертає сесію до справжнього користувача; null, якщо входу «як» не було. */
    public static function leave(): ?User
    {
        $actor = self::impersonator();

        if (! $actor) {
            session()->forget(self::KEY);

            return null;
        }

        Log::info('impersonation.leave', ['actor_id' => $actor->id, 'target_id' => Auth::id()]);

        Auth::guard('web')->login($actor);
        session()->regenerate();
        session()->forget([self::KEY, 'password_hash_web']);

        return $actor;
    }
}
