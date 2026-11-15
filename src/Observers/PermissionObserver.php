<?php

namespace Teksite\Authorize\Observers;

use Teksite\Authorize\Models\Permission;
use Teksite\Authorize\Support\AuthorizationCache;

class PermissionObserver
{


    public function created(Permission $permission): void
    {
        AuthorizationCache::forgetPermissionGates();
    }


    public function updated(Permission $permission): void
    {
        if ($permission->wasChanged('title')) AuthorizationCache::forgetPermission();

    }


    public function deleted(Permission $permission): void
    {
        AuthorizationCache::forgetPermission();

    }

}
