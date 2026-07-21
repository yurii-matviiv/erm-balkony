<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * One mutual-settlement operation ("Взаєморозрахунки" module).
 *
 * Types:
 *   collection — інкасація: money taken out of the company INTO the shared
 *                balance (recipient_id is NULL).
 *   transfer   — переказ: money leaves the shared balance to a specific
 *                participant's personal account (recipient_id required).
 *
 * The shared balance is never stored — it is always computed as
 * SUM(collections) - SUM(transfers). See CLAUDE.md "Модуль Взаєморозрахунки".
 */
class Settlement extends Model
{
    protected $fillable = [
        'type',
        'recipient_id',
        'handed_by',
        'amount',
        'payment_method',
        'paid_at',
        'comment',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'date',
    ];

    public const TYPE_COLLECTION = 'collection';

    public const TYPE_TRANSFER = 'transfer';

    /** app_settings key holding comma-separated participant user ids. */
    public const PARTICIPANTS_SETTING = 'settlement_participant_ids';

    // ──────────────────────────────────────────────
    // Relations
    // ──────────────────────────────────────────────

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Who physically handed the money over (collections only). */
    public function handedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handed_by');
    }

    // ──────────────────────────────────────────────
    // Dictionaries
    // ──────────────────────────────────────────────

    /** @return array<string, string> */
    public static function typeOptions(): array
    {
        return [
            self::TYPE_COLLECTION => 'Інкасація',
            self::TYPE_TRANSFER => 'Переказ',
        ];
    }

    /**
     * Same keys as payment_method in order_payments/expenses (cash /
     * cashless) so cross-module reports need no mapping; 'cashless' is
     * labelled "На рахунок" here — the user's wording for this module.
     *
     * @return array<string, string>
     */
    public static function paymentMethodOptions(): array
    {
        return [
            'cash' => 'Готівка',
            'cashless' => 'На рахунок',
        ];
    }

    // ──────────────────────────────────────────────
    // Participants (configured, never hardcoded)
    // ──────────────────────────────────────────────

    /**
     * The real users money can be transferred to (Юрій, Сергій, ...).
     *
     * Stored in app_settings as comma-separated user ids so nothing is
     * hardcoded (принцип "жодних зашитих цифр"); order is preserved —
     * it defines the order of the "+ Переказ …" buttons and indicators.
     *
     * @return Collection<int, User>
     */
    public static function participants(): Collection
    {
        $ids = array_values(array_filter(array_map(
            'intval',
            explode(',', (string) AppSetting::get(self::PARTICIPANTS_SETTING, '')),
        )));

        if ($ids === []) {
            return collect();
        }

        $users = User::whereIn('id', $ids)->get()->keyBy('id');

        // Re-order to match the stored order (whereIn does not guarantee it)
        return collect($ids)
            ->map(fn (int $id) => $users->get($id))
            ->filter()
            ->values();
    }

    /** @param  array<int, int|string>  $ids */
    public static function saveParticipants(array $ids): void
    {
        AppSetting::set(self::PARTICIPANTS_SETTING, implode(',', array_map('intval', $ids)));
    }
}
