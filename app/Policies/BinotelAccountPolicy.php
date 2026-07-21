<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\BinotelAccount;
use Illuminate\Auth\Access\HandlesAuthorization;

class BinotelAccountPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:BinotelAccount');
    }

    public function view(AuthUser $authUser, BinotelAccount $binotelAccount): bool
    {
        return $authUser->can('View:BinotelAccount');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:BinotelAccount');
    }

    public function update(AuthUser $authUser, BinotelAccount $binotelAccount): bool
    {
        return $authUser->can('Update:BinotelAccount');
    }

    public function delete(AuthUser $authUser, BinotelAccount $binotelAccount): bool
    {
        return $authUser->can('Delete:BinotelAccount');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:BinotelAccount');
    }

    public function restore(AuthUser $authUser, BinotelAccount $binotelAccount): bool
    {
        return $authUser->can('Restore:BinotelAccount');
    }

    public function forceDelete(AuthUser $authUser, BinotelAccount $binotelAccount): bool
    {
        return $authUser->can('ForceDelete:BinotelAccount');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:BinotelAccount');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:BinotelAccount');
    }

    public function replicate(AuthUser $authUser, BinotelAccount $binotelAccount): bool
    {
        return $authUser->can('Replicate:BinotelAccount');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:BinotelAccount');
    }

}