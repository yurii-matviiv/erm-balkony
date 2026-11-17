<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One fact in the journal of steps of a Lead or an Order (docs/STAGES.md): «this step happened».
 * occurred_at is NULL for legacy backfill (we only know the step was reached, not when).
 */
#[Fillable(['subject_type', 'subject_id', 'step', 'state', 'source', 'user_id', 'occurred_at', 'comment'])]
class ProcessStep extends Model
{
    protected $casts = ['occurred_at' => 'datetime'];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    public static function sourceLabels(): array
    {
        return ['auto' => 'автоматично', 'manual' => 'вручну', 'legacy' => 'зі старої системи'];
    }
}
