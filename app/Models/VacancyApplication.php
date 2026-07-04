<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['candidate_id', 'vacancy_id', 'advertising_channel', 'is_targeted', 'comment'])]
class VacancyApplication extends Model
{
    protected $casts = [
        'is_targeted' => 'boolean',
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
}
