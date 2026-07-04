<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Not used yet — see create_lead_calls_table migration docblock. Exists so
 * the future Binotel integration has a home to write to without another
 * migration round.
 */
#[Fillable(['lead_id', 'external_call_id', 'direction', 'phone', 'duration_seconds', 'recording_url', 'status', 'called_at'])]
class LeadCall extends Model
{
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
