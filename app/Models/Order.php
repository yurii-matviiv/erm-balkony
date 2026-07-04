<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'legacy_id', 'lead_id', 'client_id', 'manager_id', 'consultant_id', 'installer_id', 'surveyor_id', 'supplier_id',
    'address', 'order_type', 'contract_number', 'vendor_number', 'calculation_number',
    'square_meters', 'montage_price_m2', 'montage_price', 'montage_salary',
    'additional_price', 'additional_salary', 'measuring_price', 'gazda_price', 'cost_of_lifts',
    'total_price', 'balance', 'discount', 'bonus',
    'invoice_from_supplier', 'paid_invoice_to_supplier',
    'is_need_install', 'is_need_measuring',
    'measurement_date', 'readiness_date', 'delivery_time', 'removal_date', 'removal_request_sent',
    'montage_date', 'montage_date_2', 'montage_date_3', 'montage_date_4',
    'success_date', 'cancel_date',
    'comment', 'cancel_reason', 'client_feedback',
    'stage', 'status', 'legacy_status', 'legacy_status_new',
])]
class Order extends Model
{
    protected $casts = [
        'is_need_install' => 'boolean',
        'is_need_measuring' => 'boolean',
        'removal_request_sent' => 'boolean',
        'measurement_date' => 'date',
        'readiness_date' => 'date',
        'removal_date' => 'date',
        'montage_date' => 'date',
        'montage_date_2' => 'date',
        'montage_date_3' => 'date',
        'montage_date_4' => 'date',
        'success_date' => 'date',
        'cancel_date' => 'date',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function consultant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consultant_id');
    }

    public function installer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'installer_id');
    }

    /**
     * The responsible person for the crew — same rule as
     * LeadMeasurement::surveyor(), carried forward into the Order. Old
     * system called this `gauger_id`.
     */
    public function surveyor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'surveyor_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Payment rows synced from the old `orders_payments` table — historical
     * read-only data for now. New payments will come from the future
     * Рахунки/Оплати module. Ordered oldest-first so the table reads
     * chronologically (newest at bottom, like a ledger).
     */
    public function payments(): HasMany
    {
        return $this->hasMany(OrderPayment::class)->orderBy('paid_at')->orderBy('id');
    }

    /**
     * File links (Google Drive URLs from legacy system + future uploads).
     * Ordered by type then id — groups specification files together, then
     * supplier invoices, etc.
     */
    public function files(): HasMany
    {
        return $this->hasMany(OrderFile::class)->orderBy('type')->orderBy('id');
    }

    /** True if this order was imported from the legacy CRM (not created in new ERM). */
    public function isLegacy(): bool
    {
        return $this->legacy_id !== null;
    }

    /**
     * Type of work — labels translated 1:1 from the old system's
     * `order_type` enum (see create_orders_table migration docblock for
     * why this list mirrors the old one closely). Kept as a plain string
     * column (not a DB enum) so adding a new type later is just a new
     * array entry, no migration needed.
     *
     * @return array<string, string>
     */
    public static function orderTypeOptions(): array
    {
        return [
            'window' => 'Вікно (ПВХ)',
            'windows_plus_works' => 'Вікна + роботи',
            'window_in_cottage' => 'Вікно в котедж',
            'balcony' => 'Балкон/лоджія',
            'balcony_with_takeout' => 'Балкон з виносом',
            'turnkey_balcony' => 'Балкон під ключ',
            'balcony_cladding' => 'Обшивка балкону',
            'window_repair' => 'Ремонт вікон',
            'glass_unit_replacement' => 'Заміна склопакетів',
            'mosquito_net' => 'Москітна сітка',
            'windowsill' => 'Підвіконня',
            'internal_roller_blinds' => 'Ролети внутрішні',
            'another_appeal' => 'Інше звернення',
            'aluminium_window' => 'Алюмінієве вікно',
            'entrance_group_pvc' => 'Вхідна група ПВХ',
            'entrance_group_aluminium' => 'Вхідна група алюміній',
            'glazing_terrace_aluminium' => 'Скління тераси алюміній',
            'glazing_terrace_pvc' => 'Скління тераси ПВХ',
            'frameless_glazing' => 'Безрамне скління',
            'sliding_system_cold' => 'Розсувна система холодна',
            'sliding_system_warm' => 'Розсувна система тепла',
        ];
    }

    /**
     * Clean re-derivation of the old `status_new` (1-10, against a
     * `statuses` lookup table) — see class... actually migration docblock
     * for the full reasoning. Always moves forward; `statusOptions()`
     * below is the separate open/done/cancelled axis.
     *
     * @return array<string, string>
     */
    public static function stageOptions(): array
    {
        return [
            'new' => 'Нове замовлення',
            'in_handling' => 'В обробці',
            'measuring' => 'Замір',
            'in_process' => 'В процесі',
            'in_work' => 'В роботі',
            'ready' => 'Виготовлено',
            'montage' => 'Монтаж',
            'success' => 'Змонтовано',
            'closed' => 'Закрите',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            'open' => 'В роботі',
            'done' => 'Виконано',
            'cancelled' => 'Відмінено',
        ];
    }
}
