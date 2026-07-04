<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A person who applied (or might apply again) for a job. See the migration
 * docblock for why phone isn't unique and why this isn't merged with
 * Client/Supplier.
 */
#[Fillable(['legacy_id', 'phone', 'last_name', 'first_name', 'middle_name', 'email'])]
class Candidate extends Model
{
    public function applications(): HasMany
    {
        return $this->hasMany(VacancyApplication::class);
    }

    /**
     * Convenience display label used in the VacancyApplication form/table
     * (e.g. "Петренко Іван (380991234567)") so a candidate is recognizable
     * without joining several columns by hand everywhere.
     */
    public function getFullNameAttribute(): string
    {
        $name = trim(implode(' ', array_filter([$this->last_name, $this->first_name, $this->middle_name])));

        return $name !== '' ? $name : 'Без імені';
    }
}
