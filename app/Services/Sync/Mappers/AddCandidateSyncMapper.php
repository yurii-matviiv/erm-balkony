<?php

namespace App\Services\Sync\Mappers;

use App\Models\Vacancy;
use App\Services\Sync\AbstractSyncMapper;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Syncs `add_candidate` (a second, smaller old hiring-intake form, 44
 * rows — `name_candidate` is one combined field rather than split into
 * last/first/middle, and `position_candidate` only ever has 'installer'
 * or 'assistant') into the SAME new `candidates` table that
 * CandidatesSyncMapper writes to.
 *
 * Because two different old tables feed one new table, this mapper offsets
 * its legacy_id by LEGACY_ID_OFFSET to avoid colliding with
 * CandidatesSyncMapper's ids (old `candidates`.id and old
 * `add_candidate`.id both start at 1) — see resolveLegacyId() and
 * syncedQuery() below, and the docblock on AbstractSyncMapper::resolveLegacyId().
 */
class AddCandidateSyncMapper extends AbstractSyncMapper
{
    public const LEGACY_ID_OFFSET = 1_000_000;

    public function key(): string
    {
        return 'add_candidate';
    }

    public function label(): string
    {
        return 'Кандидати (друга форма заявки)';
    }

    public function oldTable(): string
    {
        return 'add_candidate';
    }

    public function newTable(): string
    {
        return 'candidates';
    }

    public function fieldMap(): array
    {
        return [
            ['old' => 'id (+1 000 000)', 'new' => 'legacy_id', 'note' => 'зміщено, щоб не конфліктувати з id з таблиці candidates — див. CandidatesSyncMapper'],
            ['old' => 'tel_number', 'new' => 'phone', 'note' => 'копіюється як є'],
            ['old' => 'name_candidate', 'new' => 'last_name', 'note' => 'ПІБ тут одним полем у старій системі — не розбиваємо, кладемо все в "Прізвище"'],
            ['old' => 'position_candidate', 'new' => 'vacancy_applications.vacancy_id', 'note' => 'installer→Монтажник, assistant→Помічник'],
            ['old' => 'comment_why / comment_why_not', 'new' => 'vacancy_applications.comment', 'note' => "об'єднується в один коментар"],
            ['old' => 'date_create_candidate', 'new' => 'vacancy_applications.created_at', 'note' => 'дата подачі заявки зберігається оригінальна'],
        ];
    }

    protected function resolveLegacyId(array $oldRow): int
    {
        return self::LEGACY_ID_OFFSET + (int) $oldRow[$this->oldPrimaryKey];
    }

    protected function syncedQuery(): Builder
    {
        return parent::syncedQuery()->where('legacy_id', '>=', self::LEGACY_ID_OFFSET);
    }

    protected function transformRow(array $oldRow): array
    {
        return [
            'phone' => $oldRow['tel_number'] ?? '',
            'last_name' => $oldRow['name_candidate'] ?? null,
            'first_name' => null,
            'middle_name' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    protected function afterUpsert(array $oldRow, int $newId): void
    {
        $vacancyName = Vacancy::legacyNameFor($oldRow['position_candidate'] ?? null);
        $vacancyId = $vacancyName ? Vacancy::firstOrCreate(['name' => $vacancyName])->id : null;

        $comment = trim(implode("\n", array_filter([
            ! empty($oldRow['comment_why']) ? 'Чому: '.$oldRow['comment_why'] : null,
            ! empty($oldRow['comment_why_not']) ? 'Чому ні: '.$oldRow['comment_why_not'] : null,
        ])));

        $applicationDate = $oldRow['date_create_candidate'] ?? null;

        $data = [
            'vacancy_id' => $vacancyId,
            'comment' => $comment !== '' ? $comment : null,
            'updated_at' => now(),
        ];

        $existingId = DB::table('vacancy_applications')->where('candidate_id', $newId)->value('id');

        if ($existingId) {
            DB::table('vacancy_applications')->where('id', $existingId)->update($data);
        } else {
            DB::table('vacancy_applications')->insert(array_merge($data, [
                'candidate_id' => $newId,
                'is_targeted' => false,
                'created_at' => $applicationDate ?: now(),
            ]));
        }
    }
}
