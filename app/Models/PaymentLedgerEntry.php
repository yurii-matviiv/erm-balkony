<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * READ-ONLY row of the `payments_ledger` SQL view (order_payments UNION
 * expenses) — the dataset behind the "Платежі" page. See the
 * create_payments_ledger_view migration docblock for the column mapping
 * and CLAUDE.md "Платежі — принципи" for the module's rules.
 *
 * Writing always goes to the REAL row (`source` + `source_id` →
 * OrderPayment or Expense) — never through this model.
 */
class PaymentLedgerEntry extends Model
{
    protected $table = 'payments_ledger';

    /** Synthetic string key ('op-123' / 'ex-45') from the view. */
    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = ['*'];

    protected $casts = [
        'paid_at' => 'date',
        'amount' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function fopAccount(): BelongsTo
    {
        return $this->belongsTo(PrivatbankAccount::class, 'fop_account_id');
    }

    /** The real underlying Expense row (only for source = 'expense'). */
    public function expense(): ?Expense
    {
        return $this->source === 'expense' ? Expense::find($this->source_id) : null;
    }

    // Block accidental writes through the view.
    public function save(array $options = []): bool
    {
        return false;
    }

    public function delete(): ?bool
    {
        return false;
    }
}
