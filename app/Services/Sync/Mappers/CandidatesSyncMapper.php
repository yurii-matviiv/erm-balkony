<?php

namespace App\Services\Sync\Mappers;

use App\Models\Vacancy;
use App\Services\Sync\AbstractSyncMapper;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Syncs `candidates` (old CRM's main hiring-form table, 194 rows) into the
 * new `candidates` table + a matching `vacancy_applications` row.
 *
 * One old row produces one new Candidate AND one new VacancyApplication.
 * The application is looked up/updated by candidate_id in afterUpsert(),
 * which keeps a re-sync idempotent without needing its own legacy_id.
 *
 * The old `job_vacancy` and `advertising_channel` enums become the new
 * vacancy_id and advertising_channel columns — see Vacancy::legacyNameFor()
 * for the (small, fixed) mapping. advertising_channel's enum values are
 * already the exact same strings used by VacancyApplication::channelOptions(),
 * so no translation is needed there.
 *
 * Out of scope for now (richer interview fields that exist on the old
 * table — team_size, work_experience_years, skills_installer, ratings,
 * etc.): not migrated, per the initial "just get the entity in place"
 * request. Can be added to Candidate/VacancyApplication later if needed.
 */
class CandidatesSyncMapper extends AbstractSyncMapper
{
    public function key(): string
    {
        return 'candidates';
    }

    public function label(): string
    {
        return 'Кандидати (заявки на вакансії)';
    }

    public function oldTable(): string
    {
        return 'candidates';
    }

    public function newTable(): string
    {
        return 'candidates';
    }

    public function fieldMap(): array
    {
        return [
            ['old' => 'id', 'new' => 'legacy_id', 'note' => 'технічне поле, для повторної синхронізації без дублів'],
            ['old' => 'phone_number', 'new' => 'phone', 'note' => 'копіюється як є'],
            ['old' => 'last_name / first_name / middle_name', 'new' => 'те саме', 'note' => 'копіюється як є'],
            ['old' => 'job_vacancy (enum)', 'new' => 'vacancy_applications.vacancy_id', 'note' => 'installer→Монтажник, assistant→Помічник, manager→Менеджер; not_selected пропускається'],
            ['old' => 'advertising_channel (enum)', 'new' => 'vacancy_applications.advertising_channel', 'note' => 'копіюється як є — ті самі значення'],
            ['old' => 'is_target', 'new' => 'vacancy_applications.is_targeted', 'note' => 'копіюється як є'],
            ['old' => 'application_date', 'new' => 'vacancy_applications.created_at', 'note' => 'дата подачі заявки зберігається оригінальна'],
        ];
    }

    /**
     * `add_candidate` (a separate, smaller old intake form) ALSO writes
     * into this same `candidates` new table, using legacy_id values offset
     * by AddCandidateSyncMapper::LEGACY_ID_OFFSET. Without this filter,
     * this mapper's counts/lists would include AddCandidateSyncMapper's
     * rows too.
     */
    protected function syncedQuery(): Builder
    {
        return parent::syncedQuery()->where('legacy_id', '<', AddCandidateSyncMapper::LEGACY_ID_OFFSET);
    }

    protected function transformRow(array $oldRow): array
    {
        return [
            'phone' => $oldRow['phone_number'] ?? '',
            'last_name' => $oldRow['last_name'] ?? null,
            'first_name' => $oldRow['first_name'] ?? null,
            'middle_name' => $oldRow['middle_name'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    protected function afterUpsert(array $oldRow, int $newId): void
    {
        $vacancyName = Vacancy::legacyNameFor($oldRow['job_vacancy'] ?? null);
        $vacancyId = $vacancyName ? Vacancy::firstOrCreate(['name' => $vacancyName])->id : null;

        $applicationDate = $oldRow['application_date'] ?? null;

        $data = [
            'vacancy_id' => $vacancyId,
            'advertising_channel' => $oldRow['advertising_channel'] ?? null,
            'is_targeted' => (bool) ($oldRow['is_target'] ?? false),
            'updated_at' => now(),
        ];

        $existingId = DB::table('vacancy_applications')->where('candidate_id', $newId)->value('id');

        if ($existingId) {
            DB::table('vacancy_applications')->where('id', $existingId)->update($data);
        } else {
            DB::table('vacancy_applications')->insert(array_merge($data, [
                'candidate_id' => $newId,
                'created_at' => $applicationDate ?: now(),
            ]));
        }
    }
}
