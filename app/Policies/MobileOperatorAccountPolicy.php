<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\MobileOperatorAccount;
use Illuminate\Auth\Access\HandlesAuthorization;

class MobileOperatorAccountPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:MobileOperatorAccount');
    }

    public function view(AuthUser $authUser, MobileOperatorAccount $mobileOperatorAccount): bool
    {
        return $authUser->can('View:MobileOperatorAccount');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:MobileOperatorAccount');
    }

    public function update(AuthUser $authUser, MobileOperatorAccount $mobileOperatorAccount): bool
    {
        return $authUser->can('Update:MobileOperatorAccount');
    }

    public function delete(AuthUser $authUser, MobileOperatorAccount $mobileOperatorAccount): bool
    {
        return $authUser->can('Delete:MobileOperatorAccount');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:MobileOperatorAccount');
    }

    public function restore(AuthUser $authUser, MobileOperatorAccount $mobileOperatorAccount): bool
    {
        return $authUser->can('Restore:MobileOperatorAccount');
    }

    public function forceDelete(AuthUser $authUser, MobileOperatorAccount $mobileOperatorAccount): bool
    {
        return $authUser->can('ForceDelete:MobileOperatorAccount');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:MobileOperatorAccount');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:MobileOperatorAccount');
    }

    public function replicate(AuthUser $authUser, MobileOperatorAccount $mobileOperatorAccount): bool
    {
        return $authUser->can('Replicate:MobileOperatorAccount');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:MobileOperatorAccount');
    }

}