<?php

namespace App\Services\Sync\Contracts;

use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Contract for a single "old table -> new table" sync definition.
 *
 * Every pair of tables we want to migrate from the legacy CRM gets its own
 * class implementing this interface (see App\Services\Sync\Mappers). Each
 * mapper class is intentionally self-contained and readable: it should be
 * possible to understand exactly what is copied, and how, just by reading
 * one file.
 *
 * IMPORTANT: implementations must never write to the "legacy" connection.
 * It is a read-only data source — see config/database.php ("legacy").
 */
interface SyncMapper
{
    /**
     * Unique, URL-safe identifier for this mapper (e.g. "users").
     * Used as the route parameter for the detail page.
     */
    public function key(): string;

    /**
     * Human-readable label shown in the UI (Ukrainian, since the whole
     * interface of this project is in Ukrainian).
     */
    public function label(): string;

    /**
     * Name of the table being read from the OLD (legacy) database.
     */
    public function oldTable(): string;

    /**
     * Name of the table being written to in the NEW (current) database.
     */
    public function newTable(): string;

    /**
     * Total number of rows available in the old table.
     */
    public function oldCount(): int;

    /**
     * Number of NEW rows that are linked to an old row via legacy_id,
     * i.e. rows that have already been synced at least once.
     */
    public function syncedCount(): int;

    /**
     * Describes how old fields map onto new fields, for display on the
     * detail page. Each entry: ['old' => string, 'new' => string, 'note' => string].
     *
     * @return array<int, array{old: string, new: string, note: string}>
     */
    public function fieldMap(): array;

    /**
     * One page of raw rows from the OLD table, newest first.
     */
    public function oldRecords(int $page, int $perPage): LengthAwarePaginator;

    /**
     * One page of already-synced rows from the NEW table (only rows that
     * have a legacy_id, i.e. rows that came from the sync, not rows created
     * directly in the new system).
     */
    public function newRecords(int $page, int $perPage): LengthAwarePaginator;

    /**
     * Runs the sync: reads every row from the old table and upserts the
     * matching row in the new table (matched by legacy_id), so running this
     * multiple times never creates duplicates.
     *
     * @return array{created: int, updated: int, skipped: int}
     */
    public function run(): array;
}
