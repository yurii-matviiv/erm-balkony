<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'is_active', 'comment'])]
class Vacancy extends Model
{
    public function applications(): HasMany
    {
        return $this->hasMany(VacancyApplication::class);
    }

    /**
     * Every logged page load of this vacancy's public form — see
     * VacancyFormView. Used by "Воронка вакансій" for the
     * views/unique-visitors/conversion numbers.
     */
    public function formViews(): HasMany
    {
        return $this->hasMany(VacancyFormView::class);
    }

    /**
     * Full public URL for the Instagram-ad landing form, or null if this
     * vacancy has no `slug` yet (i.e. no campaign points at it).
     */
    public function getPublicFormUrlAttribute(): ?string
    {
        return $this->slug ? route('vacancy-form.show', $this->slug) : null;
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
