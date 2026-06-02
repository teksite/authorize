<?php

namespace Teksite\Authorize\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Teksite\Authorize\Models\Permission;
use Teksite\Authorize\Models\Role;

trait HasAuthorization
{
    /**
     * Cache duration in seconds (default: 1 hour)
     */
    protected int $cacheDuration = 3600;

    /**
     * Cache prefix for permissions
     */
    protected string $cachePrefix = 'authorize';


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
     * Sync permissions
     *
     * @param array|string|int|Permission $permissions
     * @param bool $detaching
     * @return array
     */
    public function syncPermissions(array|string|int|Permission $permissions, bool $detaching = true): array
    {
        $permissionsArray = is_array($permissions) ? $permissions : [$permissions];

        $filteredIds = $this->filterPermissionsItems($permissionsArray);

        if (empty($filteredIds)) {
            return [];
        }

        $result = $detaching
            ? $this->permissions()->sync($filteredIds)
            : $this->permissions()->syncWithoutDetaching($filteredIds);

        $this->clearAuthorizationCache();

        return $result;
    }


    /**
     * Sync roles
     *
     * @param array|int|string|Role $roles
     * @param bool $detaching
     * @return array|null
     */
    public function assignRole(array|int|string|Role $roles, bool $detaching = true): ?array
    {
        $rolesArray = is_array($roles) ? $roles : [$roles];

        $filteredIds = $this->filterRolesItems($rolesArray);

        if (empty($filteredIds)) {
            return [];
        }
        $result = $detaching
            ? $this->roles()->sync($filteredIds)
            : $this->roles()->syncWithoutDetaching($filteredIds);

        $this->clearAuthorizationCache();

        return $result;

    }

    /**
     * does the model have role(s)
     *
     * @param string|int|array|Role $roles
     * @param bool $any
     * @return bool
     */
    public function hasRole(string|int|array|Role $roles, bool $any = true): bool
    {
        $rolesArray = is_array($roles) ? $roles : [$roles];

        if (empty($rolesArray)) {
            return false;
        }
        $userRoles = $this->getDirectRoles(true);
        $filteredIds = $this->filterRolesItems($rolesArray);

        $intersection = array_intersect($userRoles, $filteredIds);
        $count = count($intersection);

        if ($any) {
            return $count > 0;
        }

        return $count === count($filteredIds);
    }

    /**
     * does the model have permission(s)
     *
     * @param string|int|array|Permission $permissions
     * @param bool $any
     * @return bool
     */
    public function hasPermission(string|int|array|Permission $permissions, bool $any = true): bool
    {
        $this->getAllPermissions();
        $permissionsArray = is_array($permissions) ? $permissions : [$permissions];

        if (empty($permissionsArray)) {
            return false;
        }
        if ($this->isSuperAdmin()) {
            return true;
        }

        $userPermissions = $this->getAllPermissions(true);
        $filteredIds = $this->filterPermissionsItems($permissionsArray);

        $intersection = array_intersect($userPermissions, $filteredIds);
        $count = count($intersection);

        if ($any) {
            return $count > 0;
        }

        return $count === count($permissionsArray);
    }


    /**
     * Filter and resolve permission items to IDs
     *
     * @param array $permissionsArray
     * @return array
     */
    protected function filterPermissionsItems(array $permissionsArray): array
    {

        if (empty($permissionsArray)) return [];

        $ids = [];
        $titles = [];

        foreach ($permissionsArray as $item) {
            match (true) {
                $item instanceof Permission => $ids[] = $item->id,
                is_numeric($item)               => $ids[] = $item,
                is_string($item)            => $titles[] = $item,
                default                     => null
            };
        }

        if (!empty($titles)) {
            $roleIdsFromTitles = Permission::query()
                                           ->whereIn('id', $ids)
                                           ->orWhereIn('title', $titles)
                                           ->pluck('id')
                                           ->toArray();
            $ids = array_merge($ids, $roleIdsFromTitles);
        }
        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * Filter and resolve roles items to IDs
     *
     * @param array $rolesArray
     * @return array
     */
    protected function filterRolesItems(array $rolesArray): array
    {

        if (empty($rolesArray)) return [];

        $ids = [];
        $titles = [];

        foreach ($rolesArray as $item) {
            match (true) {
                $item instanceof Role => $ids[] = $item->id,
                is_numeric($item)         => $ids[] = $item,
                is_string($item)      => $titles[] = $item,
                default               => null
            };
        }

        if (!empty($titles)) {
            $roleIdsFromTitles = Role::query()
                                     ->whereIn('id', $ids)
                                     ->orWhereIn('title', $titles)
                                     ->pluck('id')
                                     ->toArray();
            $ids = array_merge($ids, $roleIdsFromTitles);
        }

        return array_values(array_unique(array_filter($ids)));
    }


    /**
     *  get all permissions directly or by roles
     *
     * @param bool $ids : set it true to return oly ids of permissions else return id=>title
     * @return array
     */
    public function getAllPermissions(bool $ids = true): array
    {
        $groupedPermissions = Cache::remember($this->getPermissionCacheKey(), $this->getCacheDuration(), function () {
            $directPermissions = ['direct_permissions' => $this->getDirectPermissions()];
            $rolePermissions = $this->getPermissionsByRoles();
            return collect($rolePermissions)->merge($directPermissions)->toArray();
        });

        $permission = collect($groupedPermissions)
            ->reduce(fn($carry, $item) => $carry->union($item), collect())
            ->all();

        return $ids ? array_keys($permission) : $permission;

    }


    /**
     *  get permission of the model directly without considering role relation
     *
     * @return array
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
        $permissions = [];

        $this->roles->each(function ($role) use (&$permissions) {
            $permissions[$role->title] = $role->permissions->pluck('title', 'id')->toArray();
        });

        return $permissions;
    }


    /**
     * Get direct roles
     *
     * @param bool $ids : true => return only ids, false key value of id=>title
     * @return array
     */
    public function getDirectRoles(bool $ids = false): array
    {

        $roles = Cache::remember($this->getRoleCacheKey(), $this->getCacheDuration(), function () {
            return $this->roles->pluck('title', 'id')->toArray();
        });

        return $ids ? array_keys($roles) : $roles;
    }


    /**
     * Get cache key for roles
     */
    protected function getRoleCacheKey(): string
    {
        return $this->cachePrefix . ':roles:' . $this->getKey();
    }



    /**
     * Get cache TTL
     */
    protected function getCacheDuration(): string
    {
        return config('authorize.super_admin_role', $this->cacheDuration) ?? 86400;

    }


    /**
     * Get cache key for roles
     */
    protected function getHierarchyCacheKey(): string
    {
        return $this->cachePrefix . ':hierarchy:' . $this->getKey();
    }


    /**
     * Get cache key for permissions
     */
    protected function getPermissionCacheKey(): string
    {
        return $this->cachePrefix . ':permissions:' . $this->getKey();
    }

    /**
     * Get cache key for all permissions
     */
    protected function getAllPermissionsCacheKey(): string
    {
        return $this->cachePrefix . ':all_permissions:' . $this->getKey();
    }

    protected static function bootHasAuthorization(): void
    {
        static::saved(function ($model) {
            $model->clearAuthorizationCache();
        });
    }

    /**
     * Clear all authorization caches for this model
     */
    public function clearAuthorizationCache(): void
    {
        Cache::forget($this->getPermissionCacheKey());
        Cache::forget($this->getRoleCacheKey());
        Cache::forget($this->getHierarchyCacheKey());
    }

    /**
     * check if the model is super admin
     *
     * @return bool
     */
    public function isSuperAdmin(): bool
    {
        $superAdmin = config('authorize.super_admin_role', null);
        if ($superAdmin && $this->hasRole($superAdmin) ?? false) {
            return true;
        }
        return false;
    }

    /**
     * get hierarchy of the model
     *
     * @param bool $min
     * @param bool $max
     * @return array|float|null
     */
    public function hierarchy(bool $min = true, bool $max = false): array|float|null
    {
        $stats = Cache::remember($this->getHierarchyCacheKey(), $this->getCacheDuration(), function () {
            return $this->roles()
                        ->selectRaw('MIN(hierarchy) as min_hierarchy, MAX(hierarchy) as max_hierarchy')
                        ->first();
        });

        if (!$stats || ($stats->min_hierarchy === null && $stats->max_hierarchy === null)) {
            return null;
        }

        if ($min && !$max) {
            return (float)$stats->min_hierarchy;
        }

        if (!$min && $max) {
            return (float)$stats->max_hierarchy;
        }

        return [
            'min' => (float)$stats->min_hierarchy,
            'max' => (float)$stats->max_hierarchy,
        ];

    }

    /**
     * Check if model can access another model by hierarchy
     *
     * @param Model $model
     * @return bool|null if return null mean the role is not found
     */
    public function canAccessModelByHierarchy(Model $model): bool|null
    {
        if (!method_exists($model, 'hierarchy')) {
            throw new InvalidArgumentException('Hierarchy method does not exist in model: ' . get_class($model));
        }


        $min = $this->hierarchy(true, false);

        if ($min === null) {
            return false;
        }

        $minModel = $model->hierarchy(true, false);

        if ($minModel === null) {
            return null;
        }

        return $min < $minModel;

    }


    /**
     * Check if model can access role by hierarchy - optimized
     *
     * @param Role|int|string $role
     * @return bool|null if return null mean the role is not found
     */
    public function canAccessRoleByHierarchy(Role|int|string $role): bool|null
    {
        $min = $this->hierarchy(true, false);

        if ($min === null) {
            return false;
        }

        $roleModel = match (true) {
            $role instanceof Role => $role,
            is_numeric($role) => Role::find($role),
            default => Role::where('title', $role)->first(),
        };;

        if (!$roleModel) {
            return null;
        }

        return $min < $roleModel->hierarchy;
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
}
