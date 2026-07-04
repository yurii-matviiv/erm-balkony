<?php

namespace App\Services\Sync;

use App\Services\Sync\Contracts\SyncMapper;
use App\Services\Sync\Mappers\AddCandidateSyncMapper;
use App\Services\Sync\Mappers\GeneralExpensesSyncMapper;
use App\Services\Sync\Mappers\CandidatesSyncMapper;
use App\Services\Sync\Mappers\ClientsSyncMapper;
use App\Services\Sync\Mappers\CommercialFromSupplierFileSyncMapper;
use App\Services\Sync\Mappers\InvoiceFromSupplierFileSyncMapper;
use App\Services\Sync\Mappers\LeadsSyncMapper;
use App\Services\Sync\Mappers\OrderPaymentsSyncMapper;
use App\Services\Sync\Mappers\OrdersSyncMapper;
use App\Services\Sync\Mappers\PaidInvoiceToSupplierFileSyncMapper;
use App\Services\Sync\Mappers\SpecificationFileSyncMapper;
use App\Services\Sync\Mappers\SuppliersSyncMapper;
use App\Services\Sync\Mappers\UsersSyncMapper;

/**
 * Central list of every "old table -> new table" sync mapper.
 *
 * To add a new pair of tables to the sync tool later (e.g. candidates,
 * orders), write a new class in App\Services\Sync\Mappers and add one line
 * here — the sync overview/detail pages pick it up automatically.
 */
class SyncMapperRegistry
{
    /**
     * @return array<int, SyncMapper>
     */
    public static function all(): array
    {
        return [
            new UsersSyncMapper,
            new ClientsSyncMapper,
            new SuppliersSyncMapper,
            new CandidatesSyncMapper,
            new AddCandidateSyncMapper,
            // Must run AFTER UsersSyncMapper and ClientsSyncMapper — it
            // resolves client_id/manager_id via their legacy_id columns
            // and skips any row it can't resolve a client for.
            new LeadsSyncMapper,
            // Must run AFTER Users/Clients/Suppliers/Leads — resolves all
            // of client_id (required), lead_id, manager_id, consultant_id,
            // installer_id, surveyor_id, supplier_id via legacy_id.
            new OrdersSyncMapper,
            // Must run AFTER OrdersSyncMapper — resolves order_id via
            // orders.legacy_id and skips any payment whose order wasn't synced.
            new OrderPaymentsSyncMapper,
            // General expenses (office / telephone / salary) from old orders_payments
            // where order_id is NULL — skipped by OrderPaymentsSyncMapper, synced here
            // into the `expenses` table instead. No dependency on other mappers.
            new GeneralExpensesSyncMapper,
            // File links (Google Drive URLs) — all must run AFTER OrdersSyncMapper.
            // Each reads from its own old table but writes to the shared `order_files`.
            new SpecificationFileSyncMapper,
            new InvoiceFromSupplierFileSyncMapper,
            new PaidInvoiceToSupplierFileSyncMapper,
            new CommercialFromSupplierFileSyncMapper,
        ];
    }

    public static function find(string $key): ?SyncMapper
    {
        foreach (self::all() as $mapper) {
            if ($mapper->key() === $key) {
                return $mapper;
            }
        }

        return null;
    }
}
