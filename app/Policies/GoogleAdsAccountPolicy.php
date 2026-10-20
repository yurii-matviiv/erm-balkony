<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\GoogleAdsAccount;
use Illuminate\Auth\Access\HandlesAuthorization;

class GoogleAdsAccountPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:GoogleAdsAccount');
    }

    public function view(AuthUser $authUser, GoogleAdsAccount $googleAdsAccount): bool
    {
        return $authUser->can('View:GoogleAdsAccount');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:GoogleAdsAccount');
    }

    public function update(AuthUser $authUser, GoogleAdsAccount $googleAdsAccount): bool
    {
        return $authUser->can('Update:GoogleAdsAccount');
    }

    public function delete(AuthUser $authUser, GoogleAdsAccount $googleAdsAccount): bool
    {
        return $authUser->can('Delete:GoogleAdsAccount');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:GoogleAdsAccount');
    }

    public function restore(AuthUser $authUser, GoogleAdsAccount $googleAdsAccount): bool
    {
        return $authUser->can('Restore:GoogleAdsAccount');
    }

    public function forceDelete(AuthUser $authUser, GoogleAdsAccount $googleAdsAccount): bool
    {
        return $authUser->can('ForceDelete:GoogleAdsAccount');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:GoogleAdsAccount');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:GoogleAdsAccount');
    }

    public function replicate(AuthUser $authUser, GoogleAdsAccount $googleAdsAccount): bool
    {
        return $authUser->can('Replicate:GoogleAdsAccount');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:GoogleAdsAccount');
    }

}