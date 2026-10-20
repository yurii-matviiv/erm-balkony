<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One Google Ads API connection (service account + target customer ID).
 *
 * Intentionally only the gateway/account layer, same as BinotelAccount and
 * MobileOperatorAccount — future features (offline conversions, reporting)
 * should reuse this model through GoogleAdsApiService instead of storing
 * credentials in feature-specific code.
 */
#[Fillable([
    'display_name', 'customer_id', 'service_account_json', 'is_active',
])]
class GoogleAdsAccount extends Model
{
    protected $casts = [
        'service_account_json' => 'encrypted',
        'is_active' => 'boolean',
    ];

    public function setCustomerIdAttribute(string $value): void
    {
        $this->attributes['customer_id'] = preg_replace('/\D+/', '', $value);
    }

    /**
     * client_email pulled out of the stored JSON key, for display only.
     */
    public function getServiceAccountEmailAttribute(): ?string
    {
        $decoded = json_decode($this->service_account_json ?? '', true);

        return $decoded['client_email'] ?? null;
    }
}
