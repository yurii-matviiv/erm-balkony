<?php

namespace App\Services\Sync\Mappers;

use App\Services\Sync\AbstractSyncMapper;
use Illuminate\Support\Facades\DB;

/**
 * Syncs `orders_payments` (old CRM) -> `order_payments` (new system).
 *
 * Only rows that can be matched to a synced order are imported — if the
 * order wasn't synced (because its client was missing), the payment row is
 * skipped too (same pattern as every other mapper that depends on a prior
 * run). Must run AFTER OrdersSyncMapper.
 *
 * `payer_name` is resolved at sync time as a plain string so the display
 * never needs extra JOINs later (see create_order_payments_table migration
 * docblock for the full payer_type -> table mapping logic).
 *
 * The old `category = 'salary'` rows are NOT skipped — they're synced and
 * stored normally, but the EditOrder view filters them out of the main
 * payments display (same behaviour as the old CRM's `getOrderPayments()`
 * which excluded them from the order page's payment table). They'll be
 * visible in the future Зарплата module.
 */
class OrderPaymentsSyncMapper extends AbstractSyncMapper
{
    public function key(): string
    {
        return 'order_payments';
    }

    public function label(): string
    {
        return 'Оплати замовлень';
    }

    public function oldTable(): string
    {
        return 'orders_payments';
    }

    public function newTable(): string
    {
        return 'order_payments';
    }

    public function fieldMap(): array
    {
        return [
            ['old' => 'id', 'new' => 'legacy_id', 'note' => 'технічне поле, для повторної синхронізації без дублів'],
            ['old' => 'order_id', 'new' => 'order_id', 'note' => 'переводиться через orders.legacy_id; якщо замовлення не знайдено — рядок пропускається'],
            ['old' => 'type', 'new' => 'direction', 'note' => 'income/outgo — перейменовано, щоб не конфліктувати з Laravel reserved words'],
            ['old' => 'user_type', 'new' => 'payer_type', 'note' => 'client/supplier/installer/gauger/expense/office'],
            ['old' => 'user_id_by_type (resolved)', 'new' => 'payer_name', 'note' => 'ім\'я клієнта/постачальника/монтажника — визначається при синку, зберігається як рядок'],
            ['old' => 'method', 'new' => 'payment_method', 'note' => 'cash/cashless/card/courier/installer'],
            ['old' => 'amount', 'new' => 'amount', 'note' => 'копіюється як є'],
            ['old' => 'status', 'new' => 'status', 'note' => 'received/sent/pending'],
            ['old' => 'category', 'new' => 'category', 'note' => 'salary / order / office / null — для майбутнього модуля Зарплата'],
            ['old' => 'comment', 'new' => 'comment', 'note' => 'копіюється як є'],
            ['old' => 'date_create', 'new' => 'paid_at', 'note' => 'дата проведення оплати'],
            ['old' => 'date_receiving', 'new' => 'received_at', 'note' => 'дата підтвердження отримання'],
            ['old' => 'privatbank_num', 'new' => 'privatbank_num', 'note' => 'для майбутньої Приватбанк-інтеграції'],
            ['old' => 'fop_account', 'new' => 'fop_account_legacy_id', 'note' => 'raw ID ФОП-рахунку зі старої системи — FK з\'явиться в модулі "наші юридичні особи"'],
        ];
    }

    protected function transformRow(array $oldRow): array
    {
        // Skip if the order wasn't synced (missing client etc.)
        $orderId = DB::table('orders')
            ->where('legacy_id', $oldRow['order_id'])
            ->value('id');

        if (! $orderId) {
            throw new \RuntimeException(
                "OrderPayment #{$oldRow['id']}: no matching order (legacy_id={$oldRow['order_id']}) — skipping."
            );
        }

        $payerName = $this->resolvePayerName(
            (string) ($oldRow['user_type'] ?? ''),
            (int) ($oldRow['user_id_by_type'] ?? 0),
        );

        return [
            'order_id'             => $orderId,
            'direction'            => $oldRow['type'] ?? 'income',
            'payer_type'           => $oldRow['user_type'] ?? '',
            'payer_name'           => $payerName,
            'payment_method'       => $oldRow['method'] ?? null,
            'amount'               => (float) ($oldRow['amount'] ?? 0),
            'status'               => $oldRow['status'] ?? 'received',
            'category'             => $oldRow['category'] ?: null,
            'comment'              => $oldRow['comment'] ?: null,
            'paid_at'              => $oldRow['date_create']    ? date('Y-m-d', strtotime($oldRow['date_create']))    : null,
            'received_at'          => $oldRow['date_receiving'] ? date('Y-m-d', strtotime($oldRow['date_receiving'])) : null,
            'privatbank_num'       => $oldRow['privatbank_num'] ?: null,
            'fop_account_legacy_id'=> $oldRow['fop_account'] ?: null,
            'created_at'           => $oldRow['date_create'] ?? now(),
            'updated_at'           => now(),
        ];
    }

    /**
     * Resolve the human-readable name for the payer/payee at sync time.
     *
     * The old `user_id_by_type` is the PRIMARY KEY of the entity in its
     * OWN old-system table (not a legacy_id — the field literally stores
     * the old `clients.id`, `suppliers.id`, or `users.id` directly).
     */
    private function resolvePayerName(string $payerType, int $oldIdByType): ?string
    {
        if ($oldIdByType <= 0) {
            return null;
        }

        return match ($payerType) {
            // clients.id in the old system = legacy_id in ours
            'client' => DB::table('clients')
                ->where('legacy_id', $oldIdByType)
                ->value('first_name'),

            // suppliers.id in the old system = legacy_id in ours
            'supplier' => DB::table('suppliers')
                ->where('legacy_id', $oldIdByType)
                ->value('name'),

            // users.id in the old system = legacy_id in ours
            'installer', 'gauger' => DB::table('users')
                ->where('legacy_id', $oldIdByType)
                ->value('name'),

            // expense/office rows don't have a specific named payee
            default => null,
        };
    }
}
