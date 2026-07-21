<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One mobile-operator API connection (operator + phone number),
 * mirroring PrivatbankAccount: credentials encrypted via cast,
 * managed through MobileOperatorIntegrationResource.
 */
#[Fillable([
    'operator', 'phone_number', 'display_name',
    'client_id', 'client_secret', 'user_id', 'is_active',
])]
class MobileOperatorAccount extends Model
{
    protected $casts = [
        'client_secret' => 'encrypted',
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
    // Dictionaries
    // ──────────────────────────────────────────────

    /**
     * Kyivstar first (the only one actually wired up for now); the other
     * two are placeholders so the select is future-proof — adding a real
     * driver later must not require a schema change.
     *
     * @return array<string, string>
     */
    public static function operatorOptions(): array
    {
        return [
            'kyivstar' => 'Київстар',
            'vodafone' => 'Vodafone',
            'lifecell' => 'lifecell',
        ];
    }

    public function operatorLabel(): string
    {
        return self::operatorOptions()[$this->operator] ?? $this->operator;
    }
}
