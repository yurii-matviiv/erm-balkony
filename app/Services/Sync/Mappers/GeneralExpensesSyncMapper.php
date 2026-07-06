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
            ['old' => 'user_id',      'new' => 'created_by',       'note' => 'автор платежу ("хто редагує") — через users.legacy_id'],
            ['old' => '—',            'new' => 'classification_status', 'note' => 'classified лише коли є і категорія, і підкатегорія; інакше unsorted — ручний розбір на сторінці "Платежі"'],
        ];
    }

    /**
     * Reads GENERAL rows only — office/expense entries WITHOUT an order.
     *
     * A payment must exist in exactly ONE table (this bit us: 1005 salary
     * rows used to land in BOTH order_payments and expenses, double-
     * counting Витрати in the analytics). The rule now is symmetric for
     * both user_types: rows WITH a real order_id are order-tied and belong
     * to OrderPaymentsSyncMapper (order-linked salary keeps its order
     * reference there for the future Зарплата module); rows without one
     * are company-level and live here.
     */
    protected function legacyQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::connection('legacy')
            ->table($this->oldTable())
            ->whereIn('user_type', ['expense', 'office'])
            ->where(function ($q) {
                $q->whereNull('order_id')->orWhere('order_id', 0);
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
            'classification_status' => $this->classify($oldRow),
            'category'       => $oldRow['category'] ?: null,
            'sub_category'   => $oldRow['sub_category'] ?: null,
            'comment'        => $oldRow['comment'] ?: null,
            'created_by'     => $this->resolveCreatedBy($oldRow),
            'paid_at'        => $oldRow['date_create']
                ? date('Y-m-d', strtotime($oldRow['date_create']))
                : null,
            'created_at'     => $oldRow['date_create'] ?? now(),
            'updated_at'     => now(),
        ];
    }

    /**
     * Author of the entry — old `user_id` ("хто редагує" per the old DB
     * column comment) via users.legacy_id. See CLAUDE.md "Платежі —
     * принципи", принцип 4.
     */
    private function resolveCreatedBy(array $oldRow): ?int
    {
        $oldUserId = (int) ($oldRow['user_id'] ?? 0);

        if ($oldUserId <= 0) {
            return null;
        }

        return DB::table('users')->where('legacy_id', $oldUserId)->value('id');
    }

    /**
     * Migration-time classification (принцип 2, CLAUDE.md "Платежі —
     * принципи"). A general expense is 'classified' only when BOTH
     * category and sub_category are present — that pair IS the clean new
     * structure for expenses (see create_expenses_table docblock).
     * Category alone ('order'/'office' without a sub) tells too little —
     * such rows go to the "Не розібрані" queue for manual sorting.
     * Comments are deliberately NOT consulted (last-resort, migration-
     * only tool — and only via explicit rules added later).
     */
    private function classify(array $oldRow): string
    {
        if (($oldRow['category'] ?? null) === 'between_accounts') {
            return 'classified';
        }

        // Amount is deliberately NOT a criterion (per explicit user
        // feedback) — a zero-sum entry with full category/sub_category is
        // an understood record, not an unsorted one.
        $hasCategory = filled($oldRow['category'] ?? null);
        $hasSub = filled($oldRow['sub_category'] ?? null);

        return ($hasCategory && $hasSub) ? 'classified' : 'unsorted';
    }
}
