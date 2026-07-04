<?php

namespace App\Services\Sync\Mappers;

use App\Services\Sync\AbstractSyncMapper;
use Illuminate\Support\Facades\DB;

/**
 * Syncs `clients` (old CRM) -> `clients` (new system).
 *
 * Scope, on purpose:
 * - Only plain contact data is copied: name, phone, phone2, email, viber,
 *   address, comment, caller_type.
 * - The old single `name` field is written into `first_name` — NOT split
 *   into last/first/middle (that's a deliberately separate, later task —
 *   see move_name_to_first_name_on_clients_table migration). The new
 *   `clients` table has no `name` column at all anymore; `first_name` is
 *   the one real field this data lives in now, per explicit request (the
 *   old fallback-through-an-accessor design was confusing — "where is
 *   this actually stored?").
 * - `who_added_user_id` (old) becomes `manager_id` (new), but the raw old
 *   ID can't be reused as-is: new users have different IDs. We look up the
 *   new user by `legacy_id = who_added_user_id` and store ITS id. If that
 *   manager hasn't been synced yet (or never existed), manager_id is left
 *   null rather than failing the whole row — sync `users` first to get
 *   this filled in correctly.
 */
class ClientsSyncMapper extends AbstractSyncMapper
{
    public function key(): string
    {
        return 'clients';
    }

    public function label(): string
    {
        return 'Клієнти';
    }

    public function oldTable(): string
    {
        return 'clients';
    }

    public function newTable(): string
    {
        return 'clients';
    }

    public function fieldMap(): array
    {
        return [
            ['old' => 'id', 'new' => 'legacy_id', 'note' => 'технічне поле, для повторної синхронізації без дублів'],
            ['old' => 'name', 'new' => 'first_name', 'note' => 'старе єдине поле "ім\'я" пишеться в first_name як є, без розбиття на прізвище/ім\'я/по-батькові — це окрема майбутня задача'],
            ['old' => 'phone', 'new' => 'phone', 'note' => 'копіюється як є'],
            ['old' => 'phone2', 'new' => 'phone2', 'note' => 'копіюється як є'],
            ['old' => 'email', 'new' => 'email', 'note' => 'копіюється як є'],
            ['old' => 'viber', 'new' => 'viber', 'note' => 'копіюється як є'],
            ['old' => 'address', 'new' => 'address', 'note' => 'копіюється як є'],
            ['old' => 'comment', 'new' => 'comment', 'note' => 'копіюється як є'],
            ['old' => 'caller_type', 'new' => 'caller_type', 'note' => 'клієнт / постачальник / спам / інше — копіюється як є'],
            ['old' => 'who_added_user_id', 'new' => 'manager_id', 'note' => 'переводиться на новий ID через legacy_id; якщо менеджер ще не синхронізований — залишається пустим'],
            ['old' => 'created_at', 'new' => 'created_at', 'note' => 'дата створення зберігається оригінальна'],
        ];
    }

    /**
     * Preserve the old system's primary key as the new row's id — client IDs
     * appear in lead/order relations, so keeping the same numeric id avoids
     * broken cross-table references after migration. Same pattern as
     * OrdersSyncMapper::persistRow().
     */
    protected function persistRow(array $newData, array $oldRow, bool $existed): ?int
    {
        $explicitId = (int) $oldRow[$this->oldPrimaryKey];

        if ($existed) {
            DB::table($this->newTable())
                ->where('legacy_id', $newData['legacy_id'])
                ->update($newData);
        } else {
            DB::table($this->newTable())
                ->insert(array_merge(['id' => $explicitId], $newData));
        }

        return $explicitId;
    }

    protected function transformRow(array $oldRow): array
    {
        $managerId = null;

        if (! empty($oldRow['who_added_user_id'])) {
            $managerId = DB::table('users')
                ->where('legacy_id', $oldRow['who_added_user_id'])
                ->value('id');
        }

        return [
            'first_name' => (string) ($oldRow['name'] ?? ''),
            'phone' => $oldRow['phone'] ?? '',
            'phone2' => $oldRow['phone2'] ?? null,
            'email' => $oldRow['email'] ?? null,
            'viber' => $oldRow['viber'] ?? null,
            'address' => $oldRow['address'] ?? null,
            'comment' => $oldRow['comment'] ?? null,
            'caller_type' => $oldRow['caller_type'] ?? null,
            'manager_id' => $managerId,
            'created_at' => $oldRow['created_at'] ?? now(),
            'updated_at' => now(),
        ];
    }
}
