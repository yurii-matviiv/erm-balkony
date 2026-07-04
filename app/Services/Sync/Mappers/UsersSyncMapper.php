<?php

namespace App\Services\Sync\Mappers;

use App\Models\User;
use App\Services\Sync\AbstractSyncMapper;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Syncs `users` (old CRM) -> `users` (new system).
 *
 * Scope of this version:
 * - Profile data is copied (name/surname/patronymic/email/phone).
 * - The old role (table `atom_roles`) IS synced too — see LEGACY_ROLE_MAP
 *   below — but this is safe by design: every role created here starts
 *   with ZERO permissions. Having a role only makes someone show up
 *   correctly labelled in the UI; it does NOT grant any access to the
 *   admin panel or any data on its own. Real access still has to be
 *   granted by hand, by attaching permissions to a role (or to the user)
 *   from the Roles page — see UserResource / Shield.
 * - The `super_admin` role (the one real admin accounts use) is NEVER
 *   touched by this map, on purpose.
 * - The old password hash is NOT copied (different, incompatible hashing
 *   algorithm). Synced accounts get a random, unusable password.
 * - The old system stores surname ("last_name") and patronymic
 *   ("middle_name") separately, but most rows have them empty/incorrect.
 *   We copy them as-is; any cleanup happens later, directly on this table.
 */
class UsersSyncMapper extends AbstractSyncMapper
{
    /**
     * old `atom_roles`.id => new role name (Ukrainian, shown in the UI).
     * Taken directly from the `description` column of `atom_roles` in the
     * old database, so nothing is invented here.
     *
     * @var array<int, string>
     */
    private const LEGACY_ROLE_MAP = [
        1 => 'Юзер',
        2 => 'Менеджер',
        3 => 'Адмін',
        4 => 'Розробник',
        5 => 'Установщик',
        6 => 'Рекламний агент',
        7 => 'Фінансист',
        8 => 'Стажер з продажу',
        9 => 'Помічник з найму',
        10 => 'Гугл-асистент',
        11 => 'Керівник відділу продажу',
        12 => 'Керівник компанії',
    ];

    public function key(): string
    {
        return 'users';
    }

    public function label(): string
    {
        return 'Користувачі';
    }

    public function oldTable(): string
    {
        return 'users';
    }

    public function newTable(): string
    {
        return 'users';
    }

    public function fieldMap(): array
    {
        return [
            ['old' => 'id', 'new' => 'legacy_id', 'note' => 'технічне поле, для повторної синхронізації без дублів'],
            ['old' => 'name', 'new' => 'name', 'note' => 'копіюється як є'],
            ['old' => 'last_name', 'new' => 'last_name', 'note' => 'прізвище, копіюється як є (не завжди заповнено)'],
            ['old' => 'middle_name', 'new' => 'middle_name', 'note' => 'по батькові, копіюється як є (не завжди заповнено)'],
            ['old' => 'email', 'new' => 'email', 'note' => 'якщо порожній — генерується тимчасовий, унікальний'],
            ['old' => 'phone', 'new' => 'phone', 'note' => 'копіюється як є'],
            ['old' => 'created_at', 'new' => 'created_at', 'note' => 'дата створення зберігається оригінальна'],
            ['old' => '—', 'new' => 'password', 'note' => 'НЕ переноситься: ставиться випадковий, непридатний для входу пароль'],
            ['old' => 'role_id', 'new' => 'роль (Spatie)', 'note' => 'роль призначається, але без жодних прав доступу — її потрібно налаштувати вручну на сторінці "Ролі"'],
        ];
    }

    protected function transformRow(array $oldRow): array
    {
        $email = trim((string) ($oldRow['email'] ?? ''));

        // The new `users` table requires a non-empty, unique email. Old rows
        // sometimes have none (or a blank string) — give them a clearly
        // marked placeholder instead of failing the whole row.
        if ($email === '') {
            $email = 'legacy-user-'.$oldRow['id'].'@imported.local';
        }

        return [
            'name' => (string) ($oldRow['name'] ?? ''),
            'last_name' => $oldRow['last_name'] ?? null,
            'middle_name' => $oldRow['middle_name'] ?? null,
            'email' => $email,
            'phone' => $oldRow['phone'] ?? null,
            // Random, never-shared password: this account cannot log in
            // until an admin deliberately sets a real password.
            'password' => Hash::make(Str::random(40)),
            'created_at' => $oldRow['created_at'] ?? now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Gives the synced user the role that matches their old role_id.
     * The role is created on first use (with zero permissions) if it
     * doesn't exist yet — see the class docblock for why that is safe.
     *
     * Uses syncRoles() (not addRole()) so that re-running the sync after
     * someone's role changed in the old system updates it here too,
     * instead of leaving them with both the old and new role.
     */
    protected function afterUpsert(array $oldRow, int $newId): void
    {
        $legacyRoleId = (int) ($oldRow['role_id'] ?? 0);
        $roleName = self::LEGACY_ROLE_MAP[$legacyRoleId] ?? null;

        if ($roleName === null) {
            return;
        }

        $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

        $user = User::find($newId);
        $user?->syncRoles([$role]);
    }
}
