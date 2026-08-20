<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['candidate_id', 'vacancy_id', 'advertising_channel', 'is_targeted', 'skills', 'skills_other', 'comment'])]
class VacancyApplication extends Model
{
    protected $casts = [
        'is_targeted' => 'boolean',
        'skills' => 'array',
    ];

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function vacancy(): BelongsTo
    {
        return $this->belongsTo(Vacancy::class);
    }

    /**
     * Marketing/attribution snapshot captured at submission time — only
     * present for applications that came through the public form, not ones
     * entered manually by staff. See VacancyApplicationMarketingData.
     */
    public function marketingData(): HasOne
    {
        return $this->hasOne(VacancyApplicationMarketingData::class);
    }

    /**
     * Channels tracked by the old system's `advertising_channel` enum,
     * carried over as plain options here (see migration comment for why
     * this column is a free string rather than a real enum/lookup table).
     *
     * @return array<string, string>
     */
    public static function channelOptions(): array
    {
        return [
            'facebook' => 'Facebook',
            'instagram' => 'Instagram',
            'telegram' => 'Telegram',
            'tiktok' => 'TikTok',
            'work.ua' => 'Work.ua',
            'robota.ua' => 'Robota.ua',
            'olx' => 'OLX',
            'unknown' => 'Невідомо',
        ];
    }

    /**
     * Experience checkboxes offered on the public application form. Free
     * keys (not a DB enum) so new skills can be added without a migration —
     * same reasoning as channelOptions().
     *
     * @return array<string, string>
     */
    public static function skillOptions(): array
    {
        return [
            'install_windows' => 'Вмію встановлювати вікна',
            'welding' => 'Вмію виконувати зварювальні роботи',
            'interior_cladding' => 'Вмію робити внутрішню обшивку',
        ];
    }
}
