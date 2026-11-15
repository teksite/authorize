<?php

namespace Teksite\Authorize\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Teksite\Authorize\Support\AuthorizationCache;

class PermissionRole extends Pivot
{
    protected $table = 'auth_permission_role';

    public $incrementing = false;

    protected static function booted(): void
    {
        $flush = static fn(self $pivot) => AuthorizationCache::forgetPermissionRolePivot();

        static::created($flush);
        static::deleted($flush);
    }
}
