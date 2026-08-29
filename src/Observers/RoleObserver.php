<?php

namespace Teksite\Authorize\Observers;

use Teksite\Authorize\Models\Permission;
use Teksite\Authorize\Models\Role;
use Teksite\Authorize\Support\AuthorizationCache;

class RoleObserver
{
    public function creating(Role $role): void
    {
        //
    }

    public function created(Role $role): void
    {
        AuthorizationCache::forgetRole($role);
    }

    public function updating(Role $role): void
    {
        //
    }

    public function updated(Role $role): void
    {
        AuthorizationCache::forgetRole($role);
    }

    public function saving(Role $role): void
    {
        //
    }

    public function saved(Role $role): void
    {
        AuthorizationCache::forgetRole($role);
    }

    public function deleting(Role $role): void
    {
        //
    }

    public function deleted(Role $role): void
    {
        AuthorizationCache::forgetRole($role);
    }
}
