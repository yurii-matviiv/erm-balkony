<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'lead_id', 'order_id', 'kind', 'transaction_id', 'click_id_type', 'click_id', 'conversion_action_id',
    'value', 'currency', 'event_time', 'lead_state', 'status', 'skip_reason', 'send_after', 'attempts',
    'validated_at', 'sent_at', 'confirmed_at', 'google_request_id', 'last_error',
])]
class ConversionOutbox extends Model
{
    protected $table = 'conversion_outbox';

    public const KIND_QUALIFIED = 'qualified';

    public const KIND_SALE = 'sale';

    public const STATUS_PENDING = 'pending';

    public const STATUS_VALIDATED = 'validated';

    public const STATUS_SENT = 'sent';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_SKIPPED = 'skipped';

    protected $casts = [
        'value' => 'decimal:2',
        'event_time' => 'datetime',
        'send_after' => 'datetime',
        'validated_at' => 'datetime',
        'sent_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
