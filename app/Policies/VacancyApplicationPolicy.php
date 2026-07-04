<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\VacancyApplication;
use Illuminate\Auth\Access\HandlesAuthorization;

class VacancyApplicationPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:VacancyApplication');
    }

    public function view(AuthUser $authUser, VacancyApplication $vacancyApplication): bool
    {
        return $authUser->can('View:VacancyApplication');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:VacancyApplication');
    }

    public function update(AuthUser $authUser, VacancyApplication $vacancyApplication): bool
    {
        return $authUser->can('Update:VacancyApplication');
    }

    public function delete(AuthUser $authUser, VacancyApplication $vacancyApplication): bool
    {
        return $authUser->can('Delete:VacancyApplication');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:VacancyApplication');
    }

    public function restore(AuthUser $authUser, VacancyApplication $vacancyApplication): bool
    {
        return $authUser->can('Restore:VacancyApplication');
    }

    public function forceDelete(AuthUser $authUser, VacancyApplication $vacancyApplication): bool
    {
        return $authUser->can('ForceDelete:VacancyApplication');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:VacancyApplication');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:VacancyApplication');
    }

    public function replicate(AuthUser $authUser, VacancyApplication $vacancyApplication): bool
    {
        return $authUser->can('Replicate:VacancyApplication');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:VacancyApplication');
    }

}