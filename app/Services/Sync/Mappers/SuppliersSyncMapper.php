<?php

namespace App\Services\Sync\Mappers;

use App\Models\SupplierContact;
use App\Services\Sync\AbstractSyncMapper;
use Illuminate\Support\Facades\DB;

/**
 * Syncs `suppliers` (old CRM) -> `suppliers` (new system).
 *
 * The old table is flat and only supports exactly two hardcoded contact
 * slots (manager_name/phone/email, manager2_name/phone/email) and has no
 * payment/billing fields at all. The new system stores contacts and
 * payment profiles as separate child tables (see Supplier model) so a
 * supplier can have any number of either — this mapper only seeds the
 * contacts that exist in the old data; payment profiles are not in the old
 * system and are added manually afterwards.
 *
 * Contacts are only seeded the FIRST time a supplier is synced (see
 * afterUpsert): if the supplier already has any contacts (e.g. the user
 * added more manually, or edited the seeded ones), re-running the sync
 * leaves them alone instead of duplicating or overwriting manual edits.
 *
 * Note: the old DB also has commercial_from_supplier, invoice_from_supplier
 * and paid_invoice_to_supplier tables (offers/invoices tied to suppliers).
 * Those are out of scope for now — this mapper only brings over the
 * supplier directory itself.
 */
class SuppliersSyncMapper extends AbstractSyncMapper
{
    public function key(): string
    {
        return 'suppliers';
    }

    public function label(): string
    {
        return 'Постачальники';
    }

    public function oldTable(): string
    {
        return 'suppliers';
    }

    public function newTable(): string
    {
        return 'suppliers';
    }

    public function fieldMap(): array
    {
        return [
            ['old' => 'id', 'new' => 'legacy_id', 'note' => 'технічне поле, для повторної синхронізації без дублів'],
            ['old' => 'company_name', 'new' => 'name', 'note' => 'копіюється як є'],
            ['old' => 'comment', 'new' => 'notes', 'note' => 'копіюється як є'],
            ['old' => 'manager_name / manager_phone / manager_email', 'new' => 'supplier_contacts (1-й контакт)', 'note' => 'створюється окремим контактом лише при першій синхронізації цього постачальника'],
            ['old' => 'manager2_name / manager2_phone / manager2_email', 'new' => 'supplier_contacts (2-й контакт)', 'note' => 'те саме, якщо поле непусте'],
            ['old' => 'address', 'new' => '— (поки не переноситься)', 'note' => 'у новій таблиці постачальників немає окремого поля адреси; за потреби додамо'],
        ];
    }

    protected function transformRow(array $oldRow): array
    {
        return [
            'name' => (string) ($oldRow['company_name'] ?? ''),
            'notes' => $oldRow['comment'] ?? null,
            'created_at' => $oldRow['created_at'] ?? now(),
            'updated_at' => now(),
        ];
    }

    protected function afterUpsert(array $oldRow, int $newId): void
    {
        $alreadyHasContacts = DB::table('supplier_contacts')
            ->where('supplier_id', $newId)
            ->exists();

        if ($alreadyHasContacts) {
            return;
        }

        // Old columns are literally manager_name/manager_phone/manager_email
        // for the first contact, and manager2_name/manager2_phone/manager2_email
        // for the second — listed explicitly here rather than built from a
        // pattern, since the old naming isn't quite consistent (manager_ vs manager2_).
        $contactColumnSets = [
            ['name' => 'manager_name', 'phone' => 'manager_phone', 'email' => 'manager_email'],
            ['name' => 'manager2_name', 'phone' => 'manager2_phone', 'email' => 'manager2_email'],
        ];

        foreach ($contactColumnSets as ['name' => $oldNameColumn, 'phone' => $oldPhoneColumn, 'email' => $oldEmailColumn]) {
            $name = trim((string) ($oldRow[$oldNameColumn] ?? ''));

            if ($name === '') {
                continue;
            }

            SupplierContact::create([
                'supplier_id' => $newId,
                'name' => $name,
                'phone' => $oldRow[$oldPhoneColumn] ?? null,
                'email' => $oldRow[$oldEmailColumn] ?? null,
            ]);
        }
    }
}
