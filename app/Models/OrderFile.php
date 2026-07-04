<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A file (or Google Drive link) attached to an Order.
 *
 * Imported from the old system's four per-type tables
 * (specification_file, invoice_from_supplier, etc.) via sync mappers.
 * Future files uploaded in the new system will also use this model.
 *
 * `legacy_source_table` + `legacy_id` uniquely identify the source row
 * in the old DB — used for idempotent re-sync.
 */
class OrderFile extends Model
{
    protected $fillable = [
        'order_id',
        'invoice_id',
        'type',
        'file_name',
        'url',
        'legacy_source_table',
        'legacy_id',
    ];

    // ──────────────────────────────────────────────
    // Relations
    // ──────────────────────────────────────────────

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    // ──────────────────────────────────────────────
    // Labels & helpers
    // ──────────────────────────────────────────────

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            'specification'     => 'Специфікація до договору',
            'supplier_invoice'  => 'Рахунок від постачальника',
            'paid_invoice'      => 'Оплачений рахунок постачальнику',
            'commercial'        => 'Комерційна пропозиція',
            default             => 'Інший файл',
        };
    }

    public static function typeIcon(string $type): string
    {
        return match ($type) {
            'specification'    => '📋',
            'supplier_invoice' => '🧾',
            'paid_invoice'     => '✅',
            'commercial'       => '💼',
            default            => '📎',
        };
    }

    /** True if this file came from the legacy system (not uploaded in new ERM). */
    public function isLegacy(): bool
    {
        return $this->legacy_source_table !== null;
    }
}
