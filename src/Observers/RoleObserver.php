<?php

namespace Teksite\Authorize\Observers;

use Teksite\Authorize\Models\Role;
use Teksite\Authorize\Support\AuthorizationCache;

class RoleObserver
{
    public function saved(Role $role): void
    {
        if ($role->wasChanged(['title', 'hierarchy'])) {
            AuthorizationCache::forgetRole();
        }
    }

    public function deleted(Role $role): void
    {
        AuthorizationCache::forgetRole();
    }
}
