<?php

namespace App\Services\Leads;

use Illuminate\Database\Eloquent\Model;

/**
 * Automatic stage change for a Lead or an Order (see docs/STAGES.md). Automation moves the
 * stage FORWARD only: if the record is already at the target stage or further along, nothing happens.
 * A manager can still set any stage by hand. Funnel order = order of ::stageOptions() of the model.
 */
class StageAdvancer
{
    /** @return bool true if the stage was changed */
    public static function advance(Model $subject, string $target): bool
    {
        $order = array_keys($subject::stageOptions());
        $from = array_search($subject->stage, $order, true);
        $to = array_search($target, $order, true);

        // Unknown target = a bug in the caller, never guess. Unknown current stage = the very beginning.
        if ($to === false || ($from !== false && $from >= $to)) {
            return false;
        }

        $subject->update(['stage' => $target]);

        return true;
    }
}
