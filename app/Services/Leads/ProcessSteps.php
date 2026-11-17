<?php

namespace App\Services\Leads;

use App\Models\ProcessStep;
use Illuminate\Database\Eloquent\Model;

/**
 * The one entry point for the journal of steps (docs/STAGES.md). «The step happened» is written once
 * per subject+step (idempotent), then the stage moves forward-only to the same step.
 */
class ProcessSteps
{
    /** @return bool true if a new step was written */
    public static function reach(Model $subject, string $step, string $source = 'auto', ?int $userId = null, ?string $comment = null, bool $alsoAdvance = true, ?\DateTimeInterface $occurredAt = null): bool
    {
        $created = false;

        $exists = ProcessStep::where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->where('step', $step)
            ->where('state', 'done')
            ->exists();

        if (! $exists) {
            ProcessStep::create([
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
                'step' => $step,
                'state' => 'done',
                'source' => $source,
                'user_id' => $userId ?? auth()->id(),
                'occurred_at' => $occurredAt ?? now(),
                'comment' => $comment,
            ]);
            $created = true;
        }

        if ($alsoAdvance) {
            StageAdvancer::advance($subject, $step);
        }

        return $created;
    }
}
