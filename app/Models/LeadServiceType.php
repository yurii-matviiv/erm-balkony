<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'is_active', 'comment'])]
class LeadServiceType extends Model
{
    public function leads(): BelongsToMany
    {
        return $this->belongsToMany(Lead::class);
    }
}
