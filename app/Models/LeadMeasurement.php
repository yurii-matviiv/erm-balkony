<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One measurement appointment for a lead — see create_lead_measurements_table
 * migration for the business rules around surveyor/installer assignment.
 */
#[Fillable(['lead_id', 'scheduled_date', 'scheduled_time', 'surveyor_id', 'installer_id', 'comment'])]
class LeadMeasurement extends Model
{
    protected $casts = [
        'scheduled_date' => 'date',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * The responsible person for this job — always the surveyor, per the
     * business rule documented on the migration. Use this (not
     * installer()) wherever "who's in charge of this job" matters.
     */
    public function surveyor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'surveyor_id');
    }

    public function installer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'installer_id');
    }
}
