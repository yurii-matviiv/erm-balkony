<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ConversionOutbox;
use Illuminate\Auth\Access\HandlesAuthorization;

class ConversionOutboxPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ConversionOutbox');
    }

    public function view(AuthUser $authUser, ConversionOutbox $conversionOutbox): bool
    {
        return $authUser->can('View:ConversionOutbox');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ConversionOutbox');
    }

    public function update(AuthUser $authUser, ConversionOutbox $conversionOutbox): bool
    {
        return $authUser->can('Update:ConversionOutbox');
    }

    public function delete(AuthUser $authUser, ConversionOutbox $conversionOutbox): bool
    {
        return $authUser->can('Delete:ConversionOutbox');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ConversionOutbox');
    }

    public function restore(AuthUser $authUser, ConversionOutbox $conversionOutbox): bool
    {
        return $authUser->can('Restore:ConversionOutbox');
    }

    public function forceDelete(AuthUser $authUser, ConversionOutbox $conversionOutbox): bool
    {
        return $authUser->can('ForceDelete:ConversionOutbox');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ConversionOutbox');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ConversionOutbox');
    }

    public function replicate(AuthUser $authUser, ConversionOutbox $conversionOutbox): bool
    {
        return $authUser->can('Replicate:ConversionOutbox');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ConversionOutbox');
    }

}