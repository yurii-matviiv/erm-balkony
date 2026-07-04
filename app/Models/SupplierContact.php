<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single contact person at a supplier. Always edited inline through the
 * parent Supplier's form (Filament Repeater), never as a standalone screen.
 */
#[Fillable(['supplier_id', 'name', 'position', 'phone', 'email', 'viber', 'comment'])]
class SupplierContact extends Model
{
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
