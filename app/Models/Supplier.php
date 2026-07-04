<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A supplier company. Deliberately separate from Client — see the migration
 * comment for why suppliers are not "a type of client".
 */
#[Fillable(['legacy_id', 'name', 'notes'])]
class Supplier extends Model
{
    /**
     * People we can call/email at this supplier. Managed inline on the
     * Supplier form via a Filament Repeater (see SupplierResource), not a
     * separate CRUD screen.
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(SupplierContact::class);
    }

    /**
     * The different payer identities (FOPs / legal sub-divisions) this
     * supplier bills through. One supplier can have several.
     */
    public function paymentProfiles(): HasMany
    {
        return $this->hasMany(SupplierPaymentProfile::class);
    }
}
