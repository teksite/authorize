<?php

namespace Teksite\Authorize\Observers;

use Teksite\Authorize\Models\Permission;
use Teksite\Authorize\Support\AuthorizationCache;

class PermissionObserver
{
    public function creating(Permission $permission): void
    {
        //
    }

    public function created(Permission $permission): void
    {
        AuthorizationCache::forgetPermissionGates();
    }

    public function updating(Permission $permission): void
    {
        //
    }

    public function updated(Permission $permission): void
    {
        AuthorizationCache::forgetPermission($permission);
    }

    public function deleting(Permission $permission): void
    {
        //
    }

    public function deleted(Permission $permission): void
    {
        AuthorizationCache::forgetPermission($permission);
        AuthorizationCache::forgetPermissionGates();
    }

}
