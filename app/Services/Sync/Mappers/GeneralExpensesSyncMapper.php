<?php

namespace App\Services\Sync\Mappers;

use App\Services\Sync\AbstractSyncMapper;
use Illuminate\Support\Facades\DB;

/**
 * Syncs general (non-order) expenses from old CRM `orders_payments` →
 * new `expenses` table.
 *
 * In the old system, ALL payments lived in `orders_payments`, even
 * general office/telephone/salary expenses that weren't tied to any
 * specific order. These rows have `user_type IN ('office', 'expense')`
 * and `order_id IS NULL` (or 0).
 *
 * `OrderPaymentsSyncMapper` skips them because it requires a matching
 * order. This mapper picks them up instead and writes to `expenses` —
 * which is specifically designed for company-wide costs not tied to any
 * order. Must run independently of OrderPaymentsSyncMapper; order doesn't
 * matter relative to it.
 *
 * Column mapping:
 *   type         → direction    (income / outgo)
 *   method       → payment_method
 *   amount       → amount
 *   status       → status
 *   category     → category     (office / telephone / salary / order / tax / ...)
 *   sub_category → sub_category (electricity / rent / binotel / ...)
 *   comment      → comment
 *   date_create  → paid_at
 */
class GeneralExpensesSyncMapper extends AbstractSyncMapper
{
    public function key(): string
    {
        return 'general_expenses';
    }

    public function label(): string
    {
        return 'Загальні витрати (офіс / телефон / зарплата)';
    }

    public function oldTable(): string
    {
        return 'orders_payments';
    }

    public function newTable(): string
    {
        return 'expenses';
    }

    public function fieldMap(): array
    {
        return [
            ['old' => 'id',           'new' => 'legacy_id',       'note' => 'для ідемпотентної повторної синхронізації'],
            ['old' => 'type',         'new' => 'direction',        'note' => 'income / outgo'],
            ['old' => 'method',       'new' => 'payment_method',   'note' => 'cash / cashless / card'],
            ['old' => 'amount',       'new' => 'amount',           'note' => 'сума як є'],
            ['old' => 'status',       'new' => 'status',           'note' => 'received / pending'],
            ['old' => 'category',     'new' => 'category',         'note' => 'office / telephone / salary / order / tax'],
            ['old' => 'sub_category', 'new' => 'sub_category',     'note' => 'electricity / rent / binotel / ...'],
            ['old' => 'comment',      'new' => 'comment',          'note' => 'копіюється як є'],
            ['old' => 'date_create',  'new' => 'paid_at',          'note' => 'дата проведення платежу'],
        ];
    }

    /**
     * Reads office/expense rows that OrderPaymentsSyncMapper skips.
     *
     * Two cases:
     *   - user_type='expense' (salary, etc.): grab ALL rows regardless of order_id.
     *     OrderPaymentsSyncMapper only handles supplier/installer/gauger, so 'expense'
     *     rows are never touched by it even when they reference a specific order
     *     (manager salary rows have order_id = the order they were paid for, but are
     *     still a general expense — not an order payment in the accounting sense).
     *   - user_type='office': only rows WITHOUT an order_id. Office rows that DO have
     *     an order_id are measurement/order-tied payments handled by OrderPaymentsSyncMapper.
     */
    protected function legacyQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::connection('legacy')
            ->table($this->oldTable())
            ->where(function ($q) {
                $q->where('user_type', 'expense')
                  ->orWhere(function ($inner) {
                      $inner->where('user_type', 'office')
                            ->where(function ($q2) {
                                $q2->whereNull('order_id')->orWhere('order_id', 0);
                            });
                  });
            });
    }

    protected function transformRow(array $oldRow): array
    {
        $method = $oldRow['method'] ?? null;

        // Normalize payment method — old CRM used 'cashless', 'card', 'cash'
        // expenses table only has 'cash' / 'cashless'
        $paymentMethod = match ($method) {
            'cash'     => 'cash',
            'cashless', 'card' => 'cashless',
            default    => 'cash', // fallback
        };

        return [
            'direction'      => $oldRow['type'] ?? 'outgo',
            'payment_method' => $paymentMethod,
            'amount'         => (float) ($oldRow['amount'] ?? 0),
            'status'         => $oldRow['status'] ?? 'received',
            'category'       => $oldRow['category'] ?: null,
            'sub_category'   => $oldRow['sub_category'] ?: null,
            'comment'        => $oldRow['comment'] ?: null,
            'paid_at'        => $oldRow['date_create']
                ? date('Y-m-d', strtotime($oldRow['date_create']))
                : null,
            'created_at'     => $oldRow['date_create'] ?? now(),
            'updated_at'     => now(),
        ];
    }
}
