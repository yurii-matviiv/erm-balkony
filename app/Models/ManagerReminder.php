<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['legacy_id', 'user_id', 'lead_id', 'reminder_text', 'reminder_datetime', 'reminder_type', 'is_completed'])]
class ManagerReminder extends Model
{
    protected $casts = [
        'reminder_datetime' => 'datetime',
        'is_completed' => 'boolean',
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

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
