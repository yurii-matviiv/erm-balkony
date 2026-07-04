<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'is_active', 'comment'])]
class Vacancy extends Model
{
    public function applications(): HasMany
    {
        return $this->hasMany(VacancyApplication::class);
    }

    /**
     * Maps the old system's job_vacancy/position_candidate enum keys
     * (English, fixed to 3 values) onto the name of the seeded Vacancy row
     * — see the create_vacancies_table migration. Used by the candidate
     * sync mappers. Returns null for "not_selected" (no vacancy chosen).
     */
    public static function legacyNameFor(?string $legacyKey): ?string
    {
        return match ($legacyKey) {
            'installer' => 'Монтажник',
            'assistant' => 'Помічник',
            'manager' => 'Менеджер',
            default => null,
        };
    }
}
