<?php

namespace App\Services\Sync\Mappers;

use App\Services\Sync\AbstractSyncMapper;
use Illuminate\Support\Facades\DB;

/**
 * Syncs `orders` (old CRM, ~2.1k rows) into the new `orders` table — see
 * create_orders_table migration docblock for the full reasoning on which
 * old columns were kept/dropped/renamed.
 *
 * `client_id` is a hard requirement here (NOT NULL on the new table, same
 * as the old one) — a row whose client never got synced is skipped
 * entirely, same pattern as LeadsSyncMapper. `lead_id` is the opposite:
 * nullable, because not every historical order can be reliably traced
 * back to a synced Lead (Leads themselves were only synced from a
 * certain point on — see LeadsSyncMapper), so a missing match just
 * leaves it null instead of failing the row.
 */
class OrdersSyncMapper extends AbstractSyncMapper
{
    /**
     * Old `status_new` (1-10, looked up against the old `statuses` table)
     * -> new clean `stage`. See create_orders_table migration docblock for
     * why this split exists. 10 ("Відмінено") maps to the 'closed' stage
     * too — cancellation is recorded via `status` below, not a separate
     * stage value.
     *
     * @var array<int, string>
     */
    private const STAGE_MAP = [
        1 => 'new',
        2 => 'in_handling',
        3 => 'measuring',
        4 => 'in_process',
        5 => 'in_work',
        6 => 'ready',
        7 => 'montage',
        8 => 'success',
        9 => 'closed',
        10 => 'closed',
    ];

    public function key(): string
    {
        return 'orders';
    }

    public function label(): string
    {
        return 'Замовлення';
    }

    public function oldTable(): string
    {
        return 'orders';
    }

    public function newTable(): string
    {
        return 'orders';
    }

    public function fieldMap(): array
    {
        return [
            ['old' => 'id', 'new' => 'legacy_id', 'note' => 'технічне поле, для повторної синхронізації без дублів'],
            ['old' => 'client_id', 'new' => 'client_id', 'note' => 'переводиться через clients.legacy_id; якщо клієнт не знайдений — рядок пропускається (NOT NULL)'],
            ['old' => 'lead_id', 'new' => 'lead_id', 'note' => 'переводиться через leads.legacy_id; якщо не знайдено — залишається пустим (не всі старі ліди синхронізовані)'],
            ['old' => 'manager_id', 'new' => 'manager_id', 'note' => 'переводиться через users.legacy_id'],
            ['old' => 'consultant_id', 'new' => 'consultant_id', 'note' => 'переводиться через users.legacy_id; "якщо двоє ведуть 1 замовлення"'],
            ['old' => 'installer_id', 'new' => 'installer_id', 'note' => 'переводиться через users.legacy_id'],
            ['old' => 'gauger_id', 'new' => 'surveyor_id', 'note' => 'старе "gauger_id" (Замірник) — перейменовано під ту саму роль, що й у lead_measurements.surveyor_id'],
            ['old' => 'supplier_id', 'new' => 'supplier_id', 'note' => 'переводиться через suppliers.legacy_id; 0/порожнє — null'],
            ['old' => 'status_new', 'new' => 'stage / status', 'note' => 'стара числова воронка (1-10) розділяється на clean stage + status — див. STAGE_MAP'],
            ['old' => 'status (raw enum) + status_new (raw int)', 'new' => 'legacy_status / legacy_status_new', 'note' => 'зберігаються без змін для трасування'],
            ['old' => 'усі фінансові/дата-поля (total_price, montage_*, *_price, *_date, ...)', 'new' => 'ті самі назви', 'note' => 'копіюються як є — див. create_orders_table migration, що саме НЕ перенесено (orders_payments/orders_files/комерційні блоки постачальника)'],
        ];
    }

    /**
     * Preserve the old system's primary key as the new row's id — orders
     * appear in contract numbers, printed invoices, and URLs, so keeping
     * the same numeric id avoids breaking all of those references after
     * migration. On first sync the row is inserted with an explicit id; on
     * re-sync only the non-id columns are updated (primary keys can't be
     * changed via UPDATE, and the value wouldn't change anyway).
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
        $clientId = DB::table('clients')->where('legacy_id', $oldRow['client_id'])->value('id');

        if (! $clientId) {
            throw new \RuntimeException("Order #{$oldRow['id']}: no matching client (legacy_id={$oldRow['client_id']}) — skipping.");
        }

        $leadId = ! empty($oldRow['lead_id'])
            ? DB::table('leads')->where('legacy_id', $oldRow['lead_id'])->value('id')
            : null;

        $managerId = ! empty($oldRow['manager_id'])
            ? DB::table('users')->where('legacy_id', $oldRow['manager_id'])->value('id')
            : null;

        $consultantId = ! empty($oldRow['consultant_id'])
            ? DB::table('users')->where('legacy_id', $oldRow['consultant_id'])->value('id')
            : null;

        $installerId = ! empty($oldRow['installer_id'])
            ? DB::table('users')->where('legacy_id', $oldRow['installer_id'])->value('id')
            : null;

        // Old "gauger_id" — same role as LeadMeasurement::surveyor_id.
        $surveyorId = ! empty($oldRow['gauger_id'])
            ? DB::table('users')->where('legacy_id', $oldRow['gauger_id'])->value('id')
            : null;

        $supplierId = ! empty($oldRow['supplier_id'])
            ? DB::table('suppliers')->where('legacy_id', $oldRow['supplier_id'])->value('id')
            : null;

        $statusNew = (int) ($oldRow['status_new'] ?? 1);
        $stage = self::STAGE_MAP[$statusNew] ?? 'new';
        $status = match (true) {
            $statusNew === 10 => 'cancelled',
            in_array($statusNew, [8, 9], true) => 'done',
            default => 'open',
        };

        return [
            'client_id' => $clientId,
            'lead_id' => $leadId,
            'manager_id' => $managerId,
            'consultant_id' => $consultantId,
            'installer_id' => $installerId,
            'surveyor_id' => $surveyorId,
            'supplier_id' => $supplierId,

            'address' => $oldRow['address'] ?? '',
            'order_type' => $oldRow['order_type'] ?? null,

            'contract_number' => $oldRow['contract_number'] ?? null,
            'vendor_number' => $oldRow['vendor_number'] ?? null,
            'calculation_number' => $oldRow['calculation_number'] ?? null,

            'square_meters' => $oldRow['square_meters'] ?? null,
            'montage_price_m2' => $oldRow['montage_price_m2'] ?? null,
            'montage_price' => $oldRow['montage_price'] ?? null,
            'montage_salary' => $oldRow['montage_salary'] ?? null,
            'additional_price' => $oldRow['additional_price'] ?? null,
            'additional_salary' => $oldRow['additional_salary'] ?? null,
            'measuring_price' => $oldRow['measuring_price'] ?? null,
            'gazda_price' => $oldRow['gazda_price'] ?? null,
            'cost_of_lifts' => $oldRow['cost_of_lifts'] ?? null,
            'total_price' => $oldRow['total_price'] ?? null,
            'balance' => $oldRow['balance'] ?? null,
            'discount' => $oldRow['discount'] ?? null,
            'bonus' => $oldRow['bonus'] ?? null,

            'invoice_from_supplier' => $oldRow['invoice_from_supplier'] ?? null,
            // Old column name literally has a space in it ("paid_invoice
            // _to_supplier") — a typo baked into the old schema, kept here
            // only as the array key we read FROM, not in our own column.
            'paid_invoice_to_supplier' => $oldRow['paid_invoice _to_supplier'] ?? null,

            'is_need_install' => (bool) ($oldRow['is_need_install'] ?? true),
            'is_need_measuring' => (bool) ($oldRow['is_need_measuring'] ?? true),

            'measurement_date' => $oldRow['measurement_date'] ?? null,
            'readiness_date' => $oldRow['readiness_date'] ?? null,
            'delivery_time' => $oldRow['delivery_time'] ?: null,
            'removal_date' => $oldRow['removal_date'] ?? null,
            'removal_request_sent' => (bool) ($oldRow['removal_request_sent'] ?? false),
            'montage_date' => $oldRow['montage_date'] ?? null,
            'montage_date_2' => $oldRow['montage_date_2'] ?? null,
            'montage_date_3' => $oldRow['montage_date_3'] ?? null,
            'montage_date_4' => $oldRow['montage_date_4'] ?? null,
            'success_date' => $oldRow['success_date'] ?? null,
            'cancel_date' => $oldRow['cancel_date'] ?? null,

            'comment' => $oldRow['comment'] ?: null,
            'cancel_reason' => $oldRow['cancel_reason'] ?: null,
            'client_feedback' => $oldRow['client_feedback'] ?: null,

            'stage' => $stage,
            'status' => $status,
            'legacy_status' => $oldRow['status'] ?? null,
            'legacy_status_new' => $statusNew,

            'created_at' => $oldRow['create_date'] ?? now(),
            'updated_at' => now(),
        ];
    }
}
