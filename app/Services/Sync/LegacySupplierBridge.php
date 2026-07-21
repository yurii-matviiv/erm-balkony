<?php

namespace App\Services\Sync;

use App\Models\Supplier;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ⚠️ DELIBERATE, TEMPORARY EXCEPTION to the project rule "новий код НІКОЛИ
 * не пише у `legacy` з'єднання" (see CLAUDE.md "Що це за проєкт"). This is
 * the ONLY place in the codebase that writes to the legacy connection, and
 * it exists for exactly one reason: managers still work in the OLD CRM
 * during the parallel-run period, and they need to see newly-added
 * suppliers there too. Remove this class + the "Також додати в стару CRM"
 * toggle on SupplierResource once the old CRM is fully decommissioned
 * (see CLAUDE.md "Що це за проєкт", point 4).
 *
 * Old `suppliers` table schema (verified against dev.ERM-btv's SQL dump):
 * every column is NOT NULL with no default except `our_id_in_company`, so
 * every string field below is coalesced to '' rather than left null.
 * There is no "address" field on the new Supplier model at all (see
 * SuppliersSyncMapper docblock), so it goes over empty.
 *
 * `viber_is` in the old system was a SINGLE flag for the whole supplier
 * (the old create-modal had one Viber toggle icon), not per-contact — we
 * derive it from whether the FIRST contact has a Viber value.
 */
class LegacySupplierBridge
{
    /**
     * Insert a newly-created Supplier into the legacy `suppliers` table.
     *
     * MUST NEVER throw and must NEVER undo the already-committed new-DB
     * record: the new Supplier is saved successfully before this runs, so
     * any failure here (legacy DB unreachable, credentials wrong, or the
     * `legacy` connection removed entirely after decommission) is caught,
     * logged, and surfaced as a calm notification — never a fatal error.
     */
    public function pushToLegacy(Supplier $record): void
    {
        try {
            $contacts = $record->contacts()->orderBy('id')->get();
            $first = $contacts->get(0);
            $second = $contacts->get(1);

            $legacyId = DB::connection('legacy')->table('suppliers')->insertGetId([
                'company_name' => (string) $record->name,
                'manager_name' => (string) ($first?->name ?? ''),
                'manager_phone' => (string) ($first?->phone ?? ''),
                'manager_email' => (string) ($first?->email ?? ''),
                'manager2_name' => (string) ($second?->name ?? ''),
                'manager2_phone' => (string) ($second?->phone ?? ''),
                'manager2_email' => (string) ($second?->email ?? ''),
                'viber_is' => filled($first?->viber) ? 1 : 0,
                'address' => '',
                'comment' => (string) ($record->notes ?? ''),
            ]);

            // Link back to legacy_id so the periodic old→new auto-sync
            // (SuppliersSyncMapper, see CLAUDE.md "Архітектура синхронізації")
            // recognizes this row as ALREADY synced and upserts into the
            // SAME new record instead of creating a duplicate Supplier
            // next time it runs.
            $record->update(['legacy_id' => $legacyId]);

            Notification::make()
                ->title('Постачальника додано і в стару CRM')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Log::warning('LegacySupplierBridge: не вдалося записати постачальника у стару CRM', [
                'supplier_id' => $record->id,
                'error' => $e->getMessage(),
            ]);

            // The new-system record already exists and is unaffected —
            // this is purely informational, never a blocking error.
            Notification::make()
                ->title('Постачальника додано')
                ->body('Стара CRM недоступна — запис створено лише в новій системі.')
                ->warning()
                ->send();
        }
    }
}
