<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['legacy_id', 'user_id', 'lead_id', 'lead_measurement_id', 'reminder_text', 'reminder_datetime', 'reminder_type', 'kind', 'is_completed', 'acknowledged_at', 'notified_at', 'notify_count'])]
class ManagerReminder extends Model
{
    protected $casts = [
        'reminder_datetime' => 'datetime',
        'is_completed' => 'boolean',
        'acknowledged_at' => 'datetime',
        'notified_at' => 'datetime',
    ];

    /** @return array<string, string> */
    public static function typeOptions(): array
    {
        return [
            'important_urgent' => 'Важливі і термінові',
            'important_not_urgent' => 'Важливі, але не термінові',
            'not_important_urgent' => 'Неважливі, але термінові',
            'not_important_not_urgent' => 'Неважливі і нетермінові',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function measurement(): BelongsTo
    {
        return $this->belongsTo(LeadMeasurement::class, 'lead_measurement_id');
    }

    /** Automatic «замір» reminder that the manager has not confirmed yet and that is due. */
    public function scopeNeedsAttention($query, int $userId)
    {
        return $query->where('user_id', $userId)
            ->where('kind', 'measurement')
            ->where('is_completed', false)
            ->whereNull('acknowledged_at')
            // reminder_datetime is Kyiv wall-clock time (see config/measurement_reminders.php).
            ->where('reminder_datetime', '<=', now(config('measurement_reminders.timezone'))->toDateTimeString());
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
