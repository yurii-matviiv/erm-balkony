<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One payment row tied to an Order — synced from the old CRM's
 * `orders_payments` table. See create_order_payments_table migration
 * docblock for the full column-by-column reasoning.
 *
 * These records are HISTORICAL (read-only display) for now. New payment
 * entries will be created through the future "Рахунки/Оплати" module.
 */
#[Fillable([
    'legacy_id', 'order_id', 'direction', 'payer_type', 'payer_name',
    'payment_method', 'amount', 'status', 'category',
    'comment', 'paid_at', 'received_at',
    'privatbank_num', 'fop_account_legacy_id',
])]
class OrderPayment extends Model
{
    protected $casts = [
        'paid_at'     => 'date',
        'received_at' => 'date',
        'amount'      => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    // ──────────────────────────────────────────────
    // Human-readable labels (used in Blade views)
    // ──────────────────────────────────────────────

    public static function directionOptions(): array
    {
        return [
            'income' => 'Дохід',
            'outgo'  => 'Витрата',
        ];
    }

    public static function payerTypeOptions(): array
    {
        return [
            'client'    => 'Клієнт',
            'supplier'  => 'Постачальник',
            'installer' => 'Монтажник',
            'gauger'    => 'Замірник',
            'expense'   => 'Витрати',
            'office'    => 'Офіс',
        ];
    }

    public static function paymentMethodOptions(): array
    {
        return [
            'cash'      => 'Готівка',
            'cashless'  => 'Безготівковий',
            'card'      => 'Картка',
            'courier'   => 'Кур\'єр',
            'installer' => 'Монтажником',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            'received' => 'Отримано',
            'sent'     => 'Надіслано',
            'pending'  => 'Очікується',
        ];
    }
}
