<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Vacancy;
use Illuminate\Auth\Access\HandlesAuthorization;

class VacancyPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Vacancy');
    }

    public function view(AuthUser $authUser, Vacancy $vacancy): bool
    {
        return $authUser->can('View:Vacancy');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Vacancy');
    }

    public function update(AuthUser $authUser, Vacancy $vacancy): bool
    {
        return $authUser->can('Update:Vacancy');
    }

    public function delete(AuthUser $authUser, Vacancy $vacancy): bool
    {
        return $authUser->can('Delete:Vacancy');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Vacancy');
    }

    public function restore(AuthUser $authUser, Vacancy $vacancy): bool
    {
        return $authUser->can('Restore:Vacancy');
    }

    public function forceDelete(AuthUser $authUser, Vacancy $vacancy): bool
    {
        return $authUser->can('ForceDelete:Vacancy');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Vacancy');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Vacancy');
    }

    public function replicate(AuthUser $authUser, Vacancy $vacancy): bool
    {
        return $authUser->can('Replicate:Vacancy');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Vacancy');
    }

}