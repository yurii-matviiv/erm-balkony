<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Marketing/attribution snapshot for a `VacancyApplication` submitted
 * through the public form — one row per application (1:1), captured once
 * at the moment of submission. Same reasoning and column shape as
 * `LeadMarketingData` for sales leads: kept separate from
 * `vacancy_applications` because applications entered manually by staff in
 * the admin panel will never have any of this.
 */
#[Fillable([
    'vacancy_application_id', 'visitor_id', 'utm_source', 'utm_medium',
    'utm_campaign', 'utm_term', 'utm_content', 'referrer', 'ip_address', 'user_agent',
])]
class VacancyApplicationMarketingData extends Model
{
    public function vacancyApplication(): BelongsTo
    {
        return $this->belongsTo(VacancyApplication::class);
    }
}
