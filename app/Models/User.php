<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'last_name', 'middle_name', 'email', 'phone', 'password', 'legacy_id', 'active_role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * The role name whose sidebar this user is currently viewing — used
     * to test "what does role X see" without changing actual permissions.
     * Falls back to the user's first real role if they haven't picked one
     * (or have since lost the role they'd picked) — see NavigationResolver.
     */
    public function getActiveRoleName(): ?string
    {
        if ($this->active_role && $this->hasRole($this->active_role)) {
            return $this->active_role;
        }

        return $this->roles()->first()?->name;
    }
}
