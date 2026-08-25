<?php

namespace Teksite\Authorize\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Teksite\Authorize\Models\Permission;
use Teksite\Authorize\Models\Role;
use Teksite\Authorize\Support\AuthorizationCache;

trait HasAuthorization
{

    /**
     * @return MorphToMany
     */
    public function permissions(): MorphToMany
    {
        return $this->morphToMany(Permission::class, 'model', 'auth_permission_models');
    }

    /**
     * @return MorphToMany
     */
    public function roles(): MorphToMany
    {
        return $this->morphToMany(Role::class, 'model', 'auth_role_models');
    }

    /**
     * Sync direct permissions
     */
    public function syncPermissions(array|string|int|Permission $permissions, bool $detaching = true): array
    {
        $filteredIds = $this->resolvePermissionIds($permissions);

        $result = $detaching
            ? $this->permissions()->sync($filteredIds)
            : $this->permissions()->syncWithoutDetaching($filteredIds);

        $this->clearAuthorizationCache();

        return $result;
    }


    /**
     * Sync roles
     */
    public function assignRole(array|int|string|Role $roles, bool $detaching = true): ?array
    {

        $filteredIds = $this->resolveRoleIds($roles);

        $result = $detaching
            ? $this->roles()->sync($filteredIds)
            : $this->roles()->syncWithoutDetaching($filteredIds);

        $this->clearAuthorizationCache();

        return $result;

    }

    /**
     * Determine whether the model has one or more roles.
     */
    public function hasRole(string|int|array|Role $roles, bool $any = true): bool
    {
        $requestedIds = $this->resolveRoleIds($roles);

        if (empty($requestedIds)) return false;

        $modelRoleIds = $this->getDirectRoles(true);

        $matched = count(array_intersect($modelRoleIds, $requestedIds));

        return $any
            ? $matched > 0
            : $matched === count($requestedIds);
    }

    /**
     * does the model have permission(s)
     */
    public function hasPermission(string|int|array|Permission $permissions, bool $any = true): bool
    {
        $requestedIds = $this->resolvePermissionIds($permissions);

        if (empty($requestedIds)) return false;

        if ($this->isSuperAdmin()) return true;

        $modelPermissionIds = $this->getAllPermissions(true);

        $matched = count(array_intersect($modelPermissionIds, $requestedIds));

        return $any
            ? $matched > 0
            : $matched === count($requestedIds);
    }

    /**
     * Filter and resolve permission items to IDs
     */
    protected function resolvePermissionIds(array|string|int|Permission $permissions): array
    {
        $permissionsArray = is_array($permissions) ? $permissions : [$permissions];

        if (empty($permissionsArray)) return [];

        $ids = [];
        $titles = [];

        foreach ($permissionsArray as $item) {

            if ($item instanceof Permission) {
                $ids[] = $item->getKey();
                continue;
            }

            if (is_int($item)) {
                $ids[] = $item;
                continue;
            }

            if (is_string($item)) {
                if (is_numeric($item)) $ids[] = (int)$item;
                else   $titles[] = $item;
            }
        }

        $ids = array_values(array_unique($ids));
        $titles = array_values(array_unique($titles));

        if (empty($ids) && empty($titles)) return [];

        $query = Permission::query();

        if ($ids !== [] && $titles !== []) {
            $query->where(function ($query) use ($ids, $titles) {
                $query->whereIn('id', $ids)->orWhereIn('title', $titles);
            });
        } elseif ($ids !== []) {
            $query->whereIn('id', $ids);
        } else {
            $query->whereIn('title', $titles);
        }


        return $query->pluck('id')->toArray();
    }

    /**
     * Filter and resolve roles items to IDs
     */
    protected function resolveRoleIds(array|int|string|Role $roles): array
    {

        $rolesArray = is_array($roles) ? $roles : [$roles];

        if (empty($rolesArray)) return [];

        $ids = [];
        $titles = [];

        foreach ($rolesArray as $item) {

            if ($item instanceof Role) {
                $ids[] = $item->getKey();
                continue;
            }

            if (is_int($item)) {
                $ids[] = $item;
                continue;
            }

            if (is_string($item)) {
                if (is_numeric($item)) $ids[] = (int)$item;
                else   $titles[] = $item;
            }
        }


        $ids = array_values(array_unique($ids));
        $titles = array_values(array_unique($titles));

        if (empty($ids) && empty($titles)) return [];

        $query = Role::query();

        if ($ids !== [] && $titles !== []) {
            $query->where(function ($query) use ($ids, $titles) {
                $query->whereIn('id', $ids)->orWhereIn('title', $titles);
            });
        } elseif ($ids !== []) {
            $query->whereIn('id', $ids);
        } else {
            $query->whereIn('title', $titles);
        }

        return $query->pluck('id')->toArray();
    }

    /**
     * Get all permissions of the model.
     *
     * Includes direct permissions and role permissions.
     */
    public function getAllPermissions(bool $onlyIds = true): array
    {
        $permissions = $this->rememberAuthorizationCache($this->getPermissionCacheKey(), function (): array {
            $this->loadMissing('permissions', 'roles.permissions');

            return $this->permissions->merge($this->roles->flatMap(fn(Role $role) => $role->permissions))
                                     ->unique('id')
                                     ->pluck('title', 'id')
                                     ->toArray();
        });

        return $onlyIds
            ? array_keys($permissions)
            : $permissions;

    }

    /**
     *  get permission of the model directly without considering role relation
     */
    public function getDirectPermissions(): array
    {
        return $this->permissions()->pluck('title', 'id')->toArray();
    }

    /**
     * get permission of the model by its roles only
     *
     * @return array
     */
    public function getPermissionsByRoles(): array
    {
        $this->loadMissing('roles.permissions');

        $permissions = [];

        foreach ($this->roles as $role) {
            $permissions[$role->title] = $role->permissions->pluck('title', 'id')->toArray();
        }

        return $permissions;
    }

    /**
     * Get direct roles
     */
    public function getDirectRoles(bool $onlyIds = false): array
    {

        $roles = $this->rememberAuthorizationCache($this->getRoleCacheKey(), function (): array {
            return $this->roles->pluck('title', 'id')->toArray();
        });

        return $onlyIds ? array_keys($roles) : $roles;
    }

    /**
     * Determine whether the model is a super administrator.
     */
    public function isSuperAdmin(): bool
    {
        $role = config('authorize.super_admin_role');

        return ($role !== null) && ($this->hasRole($role));
    }

    /**
     * get hierarchy of the model
     */
    public function hierarchy(bool $min = true, bool $max = false): array|float|null
    {
        $stats = $this->rememberAuthorizationCache($this->getHierarchyCacheKey(), function () {

            return $this->roles()
                        ->selectRaw('MIN(hierarchy) as min_hierarchy, MAX(hierarchy) as max_hierarchy')
                        ->first();
        });

        if (!$stats || ($stats->min_hierarchy === null && $stats->max_hierarchy === null)) return null;

        if ($min && !$max) return $stats->min_hierarchy;


        if (!$min && $max) return $stats->max_hierarchy;


        return [
            'min' => $stats->min_hierarchy,
            'max' => $stats->max_hierarchy,
        ];

    }

    /**
     * Check hierarchy access against another authorization model.
     */
    public function canAccessModelByHierarchy(Model $model): bool|null
    {
        if (!method_exists($model, 'hierarchy')) {
            throw new InvalidArgumentException('Model must use HasAuthorization: ' . $model::class);
        }

        $myHierarchy = $this->hierarchy(true, false);

        if ($myHierarchy === null) return false;

        $targetHierarchy = $model->hierarchy(true, false);

        if ($targetHierarchy === null) return null;

        return $myHierarchy < $targetHierarchy;

    }


    /**
     * Check hierarchy access against a role.
     */
    public function canAccessRoleByHierarchy(Role|int|string $role): bool|null
    {
        $myHierarchy = $this->hierarchy();

        if ($myHierarchy === null) return false;

        $roleModel = match (true) {
            $role instanceof Role => $role,
            is_int($role)         => Role::find($role),
            is_numeric($role)     => Role::find((int)$role),
            default               => Role::query()->where('title', $role)->first(),
        };

        if (!$roleModel) return null;

        return $myHierarchy < $roleModel->hierarchy;
    }


    /**
     * Clear all authorization caches for this model
     */
    public function clearAuthorizationCache(): void
    {
        AuthorizationCache::forgetModel($this);
    }

    /**
     * Warm up cache for this model
     */
    public function warmAuthorizationCache(): void
    {
        $this->getAllPermissions();
        $this->getDirectRoles();
        $this->hierarchy();
    }

    /**
     * Get cache duration.
     */
    protected function getCacheDuration(): int
    {
        return (int)config('authorize.cache_ttl', 3600);
    }


    /**
     * Clear cache whenever the authorization model is saved.
     */
    protected static function bootHasAuthorization(): void
    {
        static::saved(
            fn(Model $model) => $model->clearAuthorizationCache()
        );
    }


    /**
     * Get cache key for roles
     */
    protected function getRoleCacheKey(): string
    {
        return AuthorizationCache::roleKey($this);
    }


    /**
     * Get hierarchy cache key.
     */
    protected function getHierarchyCacheKey(): string
    {
        return AuthorizationCache::hierarchyKey($this);
    }

    /**
     * Get permission cache key.
     */
    protected function getPermissionCacheKey(): string
    {
        return AuthorizationCache::permissionKey($this);
    }


    /**
     * Get unique cache identity for this authorization model.
     *
     * Important for User #1 and Admin #1 having the same primary key.
     */
    protected function getAuthorizationCacheIdentifier(): string
    {
        return $this->getMorphClass() . ':' . $this->getKey();
    }

    protected function rememberAuthorizationCache(string $key, \Closure $callback): mixed
    {

        if (!config('authorize.cache_enabled', true)) return $callback();

        return Cache::remember($key, $this->getCacheDuration(), $callback);
    }

}
