<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One PrivatBank Business API account, typically corresponding to a
 * single FOP (individual entrepreneur / manager).
 *
 * The `token` field is stored encrypted using Laravel's built-in
 * `encrypted` cast (AES-256-CBC, keyed from APP_KEY). The plain-text
 * value is accessible in PHP via $account->token as usual.
 *
 * See: database/migrations/2026_06_25_100000_create_privatbank_accounts_table.php
 */
#[Fillable([
    'user_id', 'display_name', 'edrpou', 'account_number',
    'token', 'user_agent', 'is_active',
])]
class PrivatbankAccount extends Model
{
    protected $casts = [
        'token'     => 'encrypted',
        'is_active' => 'boolean',
    ];

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    /**
     * Last 4 digits of the IBAN — used as a short display suffix and
     * as part of the unique privatbank_num in order_payments
     * (matching the old system's `num_doc = NUM_DOC . '-' . last4`).
     */
    public function ibanSuffix(): string
    {
        return substr($this->account_number, -4);
    }
}
