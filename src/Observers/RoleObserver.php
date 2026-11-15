<?php

namespace Teksite\Authorize\Observers;

use Teksite\Authorize\Models\Permission;
use Teksite\Authorize\Models\Role;
use Teksite\Authorize\Support\AuthorizationCache;

class RoleObserver
{
    public function created(Role $role): void
    {
        AuthorizationCache::forgetRole();
    }


    public function updated(Role $role): void
    {
        AuthorizationCache::forgetRole();
    }


    public function saved(Role $role): void
    {
        if (!$role->wasRecentlyCreated && $role->wasChanged(['title', 'hierarchy'])) AuthorizationCache::forgetRole();
    }


    public function deleted(Role $role): void
    {
        AuthorizationCache::forgetRole();
    }


}
