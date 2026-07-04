<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One billing identity (payer) a supplier invoices through — e.g. a
 * specific FOP or legal sub-division. A supplier commonly has more than
 * one of these. Always edited inline through the parent Supplier's form
 * (Filament Repeater), never as a standalone screen.
 */
#[Fillable(['supplier_id', 'payer_name', 'tax_id', 'bank_name', 'iban', 'mfo', 'comment'])]
class SupplierPaymentProfile extends Model
{
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
