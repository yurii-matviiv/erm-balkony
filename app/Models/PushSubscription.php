<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One browser/device of a user that agreed to receive Web Push notifications. */
#[Fillable(['user_id', 'endpoint', 'endpoint_hash', 'p256dh', 'auth', 'user_agent'])]
class PushSubscription extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
