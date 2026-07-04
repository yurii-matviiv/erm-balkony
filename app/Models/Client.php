<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'legacy_id', 'last_name', 'first_name', 'middle_name',
    'phone', 'phone2', 'email', 'viber',
    'address', 'street', 'house_number', 'apartment_number', 'floor', 'city',
    'comment', 'caller_type', 'manager_id',
])]
class Client extends Model
{
    /**
     * The staff member who added this client (copied from the old system's
     * `who_added_user_id`, translated to the new user via legacy_id — see
     * ClientsSyncMapper).
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * There is NO separate `name` column anymore — per explicit request,
     * the old system's single free-text name was moved into `first_name`
     * as-is during sync (see ClientsSyncMapper / the
     * move_name_to_first_name_on_clients_table migration), not kept in a
     * second, hidden field that this accessor silently fell back to.
     * Splitting it properly into last_name/first_name/middle_name is a
     * deliberately separate, later task — for now an old client's "full
     * name" is often really just sitting whole in `first_name`.
     */
    public function getFullNameAttribute(): string
    {
        $structured = trim(implode(' ', array_filter([$this->last_name, $this->first_name, $this->middle_name])));

        return $structured !== '' ? $structured : 'Без імені';
    }

    /**
     * Single-line address built from the structured fields, falling back
     * to the old free-text `address` for legacy clients that never got the
     * structured ones filled in.
     */
    public function getFullAddressAttribute(): ?string
    {
        $parts = array_filter([
            $this->city,
            $this->street,
            $this->house_number ? 'буд. '.$this->house_number : null,
            $this->apartment_number ? 'кв. '.$this->apartment_number : null,
        ]);

        return $parts !== [] ? implode(', ', $parts) : $this->address;
    }
}
