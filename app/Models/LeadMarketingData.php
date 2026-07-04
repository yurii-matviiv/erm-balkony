<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'lead_id', 'utm_url', 'utm_source', 'utm_campaign', 'utm_medium', 'utm_term', 'utm_content',
    'utm_group', 'utm_asset_group', 'site_source', 'form_name', 'form_position',
    'device', 'ip_address', 'user_fingerprint', 'client_cookie_id',
    'gclid', 'gbraid', 'wbraid', 'referrer', 'referral_1', 'referral_2', 'referral_3', 'referral_4',
])]
class LeadMarketingData extends Model
{
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
