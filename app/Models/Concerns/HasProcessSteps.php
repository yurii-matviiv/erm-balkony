<?php

namespace App\Models\Concerns;

use App\Models\ProcessStep;
use App\Services\Leads\ProcessSteps;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Journal of steps for a Lead/Order. A MANUAL stage change made by a logged-in user is recorded as a step
 * too (source «manual»), so the journal and the stage never diverge. Automatic changes go through
 * ProcessSteps::reach(), which writes the step first — then this hook finds it and does nothing.
 */
trait HasProcessSteps
{
    public static function bootHasProcessSteps(): void
    {
        static::updated(function ($model): void {
            if ($model->wasChanged('stage') && auth()->check()) {
                ProcessSteps::reach($model, (string) $model->stage, 'manual', auth()->id(), alsoAdvance: false);
            }
        });
    }

    public function steps(): MorphMany
    {
        return $this->morphMany(ProcessStep::class, 'subject')->orderByRaw('occurred_at is null desc')->orderBy('occurred_at')->orderBy('id');
    }

    public function hasStep(string $step): bool
    {
        return $this->steps()->where('step', $step)->where('state', 'done')->exists();
    }
}
