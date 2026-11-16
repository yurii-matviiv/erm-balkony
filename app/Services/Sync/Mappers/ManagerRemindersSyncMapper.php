<?php

namespace App\Services\Sync\Mappers;

use App\Services\Sync\AbstractSyncMapper;
use Illuminate\Support\Facades\DB;

/**
 * Syncs `manager_reminders` (old CRM) -> `manager_reminders`. Must run AFTER
 * Users and Leads (resolves user_id / enquiry_id via legacy_id).
 *
 * Once a reminder exists in the new system, edits made here (text, date,
 * done flag) survive later sync runs — only brand-new old reminders are added
 * with their full data (same principle as the password in UsersSyncMapper).
 */
class ManagerRemindersSyncMapper extends AbstractSyncMapper
{
    public function key(): string
    {
        return 'manager_reminders';
    }

    public function label(): string
    {
        return 'Нагадування менеджерів';
    }

    public function oldTable(): string
    {
        return 'manager_reminders';
    }

    public function newTable(): string
    {
        return 'manager_reminders';
    }

    public function fieldMap(): array
    {
        return [
            ['old' => 'id', 'new' => 'legacy_id', 'note' => 'технічне поле'],
            ['old' => 'user_id', 'new' => 'user_id', 'note' => 'через users.legacy_id'],
            ['old' => 'enquiry_id', 'new' => 'lead_id', 'note' => 'через leads.legacy_id; порожнє, якщо нагадування не до ліда'],
            ['old' => 'reminder_text / reminder_datetime / reminder_type / is_completed', 'new' => 'те саме', 'note' => 'після першого перенесення правки в новій системі не перезаписуються'],
        ];
    }

    protected function transformRow(array $oldRow): array
    {
        return [
            'user_id' => ! empty($oldRow['user_id']) ? DB::table('users')->where('legacy_id', $oldRow['user_id'])->value('id') : null,
            'lead_id' => ! empty($oldRow['enquiry_id']) ? DB::table('leads')->where('legacy_id', $oldRow['enquiry_id'])->value('id') : null,
            'reminder_text' => (string) ($oldRow['reminder_text'] ?? ''),
            'reminder_datetime' => $oldRow['reminder_datetime'] ?? now(),
            'reminder_type' => $oldRow['reminder_type'] ?: 'important_urgent',
            'is_completed' => (bool) ($oldRow['is_completed'] ?? false),
            'created_at' => $oldRow['created_at'] ?? now(),
            'updated_at' => $oldRow['updated_at'] ?? now(),
        ];
    }

    protected function persistRow(array $newData, array $oldRow, bool $existed): ?int
    {
        DB::table($this->newTable())->upsert(
            [$newData],
            ['legacy_id'],
            ['user_id', 'lead_id'],
        );

        return DB::table($this->newTable())->where('legacy_id', $newData['legacy_id'])->value('id');
    }
}
