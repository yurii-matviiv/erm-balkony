<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One Binotel API connection for the company.
 *
 * This is intentionally only the gateway/account layer. Future features
 * (call import, call matching to leads, call recordings, analytics) should
 * reuse this model through BinotelApiService instead of storing credentials
 * in feature-specific code.
 */
#[Fillable([
    'display_name', 'company_name', 'company_id', 'api_key', 'api_secret', 'is_active',
])]
class BinotelAccount extends Model
{
    protected $casts = [
        'api_secret' => 'encrypted',
        'is_active' => 'boolean',
    ];
}
