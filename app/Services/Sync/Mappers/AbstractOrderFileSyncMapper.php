<?php

namespace App\Services\Sync\Mappers;

use App\Services\Sync\AbstractSyncMapper;
use Illuminate\Support\Facades\DB;

/**
 * Base class for all four "order file" sync mappers
 * (specification, supplier invoice, paid invoice, commercial offer).
 *
 * All four old tables share the same shape:
 *   id, order_id, url, file_name  (plus minor extras like manager_id)
 *
 * They all write to the same new `order_files` table, tagged by `type`.
 * The composite unique key is (legacy_source_table, legacy_id) — so IDs
 * from different source tables (all starting from 1) never collide.
 *
 * Subclasses only need to provide:
 *   oldTable() — which old table to read from
 *   fileType() — which `type` value to write ('specification', etc.)
 */
abstract class AbstractOrderFileSyncMapper extends AbstractSyncMapper
{
    public function newTable(): string
    {
        return 'order_files';
    }

    public function fieldMap(): array
    {
        return [
            ['old' => 'id',       'new' => 'legacy_id',           'note' => 'stored with legacy_source_table for composite dedup'],
            ['old' => 'order_id', 'new' => 'order_id',            'note' => 'resolved via orders.legacy_id'],
            ['old' => 'url',      'new' => 'url',                  'note' => 'Google Drive public link'],
            ['old' => 'file_name','new' => 'file_name',            'note' => 'original filename'],
            ['old' => '—',        'new' => 'type',                 'note' => 'set by subclass via fileType()'],
            ['old' => '—',        'new' => 'legacy_source_table',  'note' => 'oldTable() value — namespaces legacy_id'],
        ];
    }

    /** The `type` value to write into `order_files.type`. */
    abstract protected function fileType(): string;

    protected function transformRow(array $oldRow): array
    {
        $orderId = DB::table('orders')
            ->where('legacy_id', $oldRow['order_id'])
            ->value('id');

        if (! $orderId) {
            throw new \RuntimeException(
                "OrderFile ({$this->oldTable()}) #{$oldRow['id']}: no matching order — skipping."
            );
        }

        return [
            'order_id'            => $orderId,
            'type'                => $this->fileType(),
            'file_name'           => $oldRow['file_name'] ?? 'file',
            'url'                 => $oldRow['url'] ?? '',
            'legacy_source_table' => $this->oldTable(),
            // legacy_id is added by AbstractSyncMapper::run() from resolveLegacyId()
            'created_at'          => now(),
            'updated_at'          => now(),
        ];
    }

    /**
     * Override: use (legacy_source_table, legacy_id) as the composite
     * unique key instead of just legacy_id, because four different source
     * tables all have their own IDs starting from 1.
     */
    protected function persistRow(array $newData, array $oldRow, bool $existed): ?int
    {
        $existing = DB::table('order_files')
            ->where('legacy_source_table', $newData['legacy_source_table'])
            ->where('legacy_id', $newData['legacy_id'])
            ->first();

        if ($existing) {
            DB::table('order_files')
                ->where('id', $existing->id)
                ->update(array_merge($newData, ['updated_at' => now()]));

            return $existing->id;
        }

        return DB::table('order_files')->insertGetId($newData);
    }

    protected function syncedQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::table('order_files')
            ->where('legacy_source_table', $this->oldTable());
    }

    public function syncedCount(): int
    {
        return $this->syncedQuery()->count();
    }
}
