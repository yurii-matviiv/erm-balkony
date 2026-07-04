<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\PrivatbankAccount;
use Illuminate\Auth\Access\HandlesAuthorization;

class PrivatbankAccountPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PrivatbankAccount');
    }

    public function view(AuthUser $authUser, PrivatbankAccount $privatbankAccount): bool
    {
        return $authUser->can('View:PrivatbankAccount');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PrivatbankAccount');
    }

    public function update(AuthUser $authUser, PrivatbankAccount $privatbankAccount): bool
    {
        return $authUser->can('Update:PrivatbankAccount');
    }

    public function delete(AuthUser $authUser, PrivatbankAccount $privatbankAccount): bool
    {
        return $authUser->can('Delete:PrivatbankAccount');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:PrivatbankAccount');
    }

    public function restore(AuthUser $authUser, PrivatbankAccount $privatbankAccount): bool
    {
        return $authUser->can('Restore:PrivatbankAccount');
    }

    public function forceDelete(AuthUser $authUser, PrivatbankAccount $privatbankAccount): bool
    {
        return $authUser->can('ForceDelete:PrivatbankAccount');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PrivatbankAccount');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PrivatbankAccount');
    }

    public function replicate(AuthUser $authUser, PrivatbankAccount $privatbankAccount): bool
    {
        return $authUser->can('Replicate:PrivatbankAccount');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PrivatbankAccount');
    }

}