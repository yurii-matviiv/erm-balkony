<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per page load of a public vacancy form (`/vacancy/{slug}`) —
 * logged BEFORE the candidate fills in anything, so this table captures
 * every ad click/open, not just completed applications. See
 * VacancyApplicationMarketingData for the equivalent snapshot taken at
 * submission time. `visitor_id` (a long-lived cookie, see
 * `App\Services\Marketing\FormAttribution`) is what lets "Воронка вакансій"
 * distinguish unique visitors from repeat opens by the same person.
 */
#[Fillable([
    'vacancy_id', 'visitor_id', 'utm_source', 'utm_medium', 'utm_campaign',
    'utm_term', 'utm_content', 'referrer', 'ip_address', 'user_agent',
])]
class VacancyFormView extends Model
{
    public function vacancy(): BelongsTo
    {
        return $this->belongsTo(Vacancy::class);
    }
}
