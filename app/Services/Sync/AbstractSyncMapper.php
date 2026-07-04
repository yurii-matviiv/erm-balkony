<?php

namespace App\Services\Sync;

use App\Services\Sync\Contracts\SyncMapper;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Shared logic for all sync mappers, so that a concrete mapper (e.g.
 * UsersSyncMapper) only has to describe WHAT to copy and HOW to transform
 * it, not how pagination/counting/upserting works.
 *
 * A concrete mapper only needs to implement:
 * - key(), label(), oldTable(), newTable(), fieldMap()
 * - transformRow(): turns one raw row from the old table into an array of
 *   columns for the new table.
 */
abstract class AbstractSyncMapper implements SyncMapper
{
    /**
     * Name of the primary key column on the OLD table. Almost always "id".
     */
    protected string $oldPrimaryKey = 'id';

    /**
     * Turns one row read from the old table (as a plain array) into the
     * array of column => value that should be written to the new table.
     * Must NOT include "legacy_id" — that is added automatically.
     *
     * @param  array<string, mixed>  $oldRow
     * @return array<string, mixed>
     */
    abstract protected function transformRow(array $oldRow): array;

    /**
     * Optional hook a concrete mapper can override to do something extra
     * once a row has been written to the new table — e.g. UsersSyncMapper
     * uses this to assign the matching role. Runs for both newly created
     * AND re-synced (updated) rows, so it stays correct if a role changes
     * in the old system later.
     *
     * @param  array<string, mixed>  $oldRow  the original row from the old table
     * @param  int  $newId  primary key of the row in the new table
     */
    protected function afterUpsert(array $oldRow, int $newId): void
    {
        // no-op by default
    }

    /**
     * The legacy_id value to write for one old row. Defaults to the raw
     * primary key. Override this when two DIFFERENT old tables sync into
     * the SAME new table (e.g. CandidatesSyncMapper and
     * AddCandidateSyncMapper both write to `candidates`) — without this,
     * old row #5 from one source could collide with old row #5 from the
     * other and overwrite it. See AddCandidateSyncMapper for an example
     * (it adds a fixed offset to keep its ids in their own range).
     *
     * @param  array<string, mixed>  $oldRow
     */
    protected function resolveLegacyId(array $oldRow): int
    {
        return (int) $oldRow[$this->oldPrimaryKey];
    }

    /**
     * Base query against the legacy source table.
     * Override in a concrete mapper to add WHERE clauses — useful when a
     * mapper reads only a subset of a large shared table (e.g. only the
     * office/expense rows from `orders_payments`).
     */
    protected function legacyQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::connection('legacy')->table($this->oldTable());
    }

    public function oldCount(): int
    {
        return $this->legacyQuery()->count();
    }

    public function syncedCount(): int
    {
        return $this->syncedQuery()->count();
    }

    /**
     * Base query for "rows in the new table that came from THIS mapper's
     * sync". Defaults to "has any legacy_id". Override alongside
     * resolveLegacyId() when another mapper writes to the same newTable()
     * with its own id range (see AddCandidateSyncMapper) — otherwise the
     * two mappers' counts/lists on the sync overview page would each show
     * the combined total of both instead of just their own rows.
     */
    protected function syncedQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::table($this->newTable())->whereNotNull('legacy_id');
    }

    public function oldRecords(int $page, int $perPage): LengthAwarePaginator
    {
        // A distinct page name ("old_page") is required because this page
        // also shows a second, independent paginator (newRecords) — without
        // this, both would fight over the same "?page=" query parameter.
        // withQueryString() keeps the other paginator's page (and ?mapper=)
        // in the generated links, instead of dropping them.
        return $this->legacyQuery()
            ->orderByDesc($this->oldPrimaryKey)
            ->paginate($perPage, page: $page, pageName: 'old_page')
            ->withQueryString();
    }

    public function newRecords(int $page, int $perPage): LengthAwarePaginator
    {
        return $this->syncedQuery()
            ->orderByDesc('legacy_id')
            ->paginate($perPage, page: $page, pageName: 'new_page')
            ->withQueryString();
    }

    /**
     * Writes one transformed row into the new table and returns the new
     * primary key, or null on failure.
     *
     * The default implementation uses upsert() matched on legacy_id (MySQL
     * INSERT … ON DUPLICATE KEY UPDATE), which lets the database assign the
     * auto-increment id freely.
     *
     * Override this when the old primary key must be preserved as the new
     * row's id — e.g. OrdersSyncMapper does this so that contract numbers,
     * printed documents and URLs that contain the order id keep working
     * after migration. The override is responsible for the insert-vs-update
     * split so it can pass the explicit id only on INSERT (you cannot change
     * a primary key via UPDATE).
     *
     * @param  array<string, mixed>  $newData  already includes 'legacy_id'
     * @param  array<string, mixed>  $oldRow   original row from the old table
     * @param  bool  $existed  true if this legacy_id was already in the new table
     * @return int|null  the id of the written row in the new table
     */
    protected function persistRow(array $newData, array $oldRow, bool $existed): ?int
    {
        DB::table($this->newTable())->upsert(
            [$newData],
            ['legacy_id'],
            array_keys($newData),
        );

        return DB::table($this->newTable())
            ->where('legacy_id', $newData['legacy_id'])
            ->value('id');
    }

    /**
     * Reads every row from the old table and upserts it into the new table,
     * matched by legacy_id. Running this again later only updates existing
     * rows — it never creates duplicates.
     *
     * This reads the legacy table in chunks to avoid loading everything
     * (e.g. all candidates/orders) into memory at once.
     */
    public function run(): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;

        $this->legacyQuery()
            ->orderBy($this->oldPrimaryKey)
            ->chunkById(200, function ($oldRows) use (&$created, &$updated, &$skipped) {
                foreach ($oldRows as $oldRow) {
                    $oldRow = (array) $oldRow;

                    // A single malformed/conflicting legacy row (e.g. a
                    // duplicate email tripping a unique constraint) should
                    // never stop the whole sync run — skip it and continue.
                    try {
                        $newData = $this->transformRow($oldRow);
                        $newData['legacy_id'] = $this->resolveLegacyId($oldRow);

                        $existed = DB::table($this->newTable())
                            ->where('legacy_id', $newData['legacy_id'])
                            ->exists();

                        $newId = $this->persistRow($newData, $oldRow, $existed);

                        if ($newId !== null) {
                            $this->afterUpsert($oldRow, (int) $newId);
                        }

                        $existed ? $updated++ : $created++;
                    } catch (\Throwable $e) {
                        $skipped++;
                    }
                }
            }, $this->oldPrimaryKey);

        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped];
    }
}
