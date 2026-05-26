<?php

namespace Teksite\Authorize\Traits;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Teksite\Authorize\Models\Permission;
use Teksite\Authorize\Models\Role;

trait HasAuthorization
{
    /**
     * Cache duration in seconds (default: 1 hour)
     */
    protected int $permissionCacheDuration = 3600;

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
     * Sync permissions with eager loading optimization
     *
     * @param array $permissions
     * @param bool $detaching
     * @return void
     */
    public function syncPermissions(array $permissions, bool $detaching = true): void
    {
        $detaching
            ? $this->permissions()->sync($permissions)
            : $this->permissions()->syncWithoutDetaching($permissions);

        // Clear permission cache after modification
        $this->clearPermissionCache();
    }

    /**
     * Assign roles to the model with optimized query
     *
     * @param Role|Role[]|string|string[]|int|int[] $roles
     * @param bool $detaching
     * @return array|null
     */
    public function assignRole(array|int|string|Role $roles, bool $detaching = true): array
    {
        $rolesArray = is_array($roles) ? $roles : [$roles];


        $filteredIds = $this->filterRoleItems($rolesArray);

        if (empty($filteredIds)) {
            return [];
        }

        $result = $detaching
            ? $this->roles()->sync($filteredIds)
            : $this->roles()->syncWithoutDetaching($filteredIds);

        // Clear permission cache after role change
        $this->clearPermissionCache();

        return $result;
    }

    /**
     * Check if model has any/all of the given roles (optimized with single query)
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

        // If roles are already loaded, use collection (no query)
        if ($this->relationLoaded('roles')) {
            $roleIds = $this->getRoleIdsFromCollection($rolesArray);
            return $any
                ? $roleIds->isNotEmpty()
                : $roleIds->count() === count($rolesArray);
        }

        // Otherwise, use a single optimized query
        $filteredIds = $this->filterRoleItems($rolesArray);

        if (empty($filteredIds)) {
            return false;
        }

        $query = $this->roles()->whereIn('id', $filteredIds);

        return $any
            ? $query->exists()
            : $query->count() === count($filteredIds);
    }

    /**
     * Check if model has permission (optimized with caching)
     *
     * @param string|int|Permission $permission
     * @param bool $useCache
     * @return bool
     */
    public function hasPermission(string|int|Permission $permission, bool $useCache = true): bool
    {
        // Super admin bypass (optional - implement your own logic)
        if ($this->isSuperAdmin()) {
            return true;
        }

        $cacheKey = $this->getPermissionCacheKey($permission);

        if ($useCache && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $result = $this->checkPermission($permission);

        if ($useCache) {
            Cache::put($cacheKey, $result, $this->permissionCacheDuration);
        }

        return $result;
    }

    /**
     * Get all permissions (direct + through roles) with eager loading optimization
     *
     * @param bool $useCache
     * @return EloquentCollection
     */
    public function getAllPermissions(bool $useCache = true): EloquentCollection
    {
        $cacheKey = "user.permissions.{$this->id}";

        if ($useCache && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        // Load relationships efficiently
        $this->loadMissing(['permissions', 'roles.permissions']);

        $directPermissions = $this->permissions;
        $rolePermissions = $this->roles->flatMap(fn($role) => $role->permissions);

        $allPermissions = $directPermissions->merge($rolePermissions)->unique('id')->values();

        if ($useCache) {
            Cache::put($cacheKey, $allPermissions, $this->permissionCacheDuration);
        }

        return $allPermissions;
    }

    /**
     * Check multiple permissions at once (batch check)
     *
     * @param array $permissions
     * @param bool $requireAll
     * @return array|bool
     */
    public function hasPermissions(array $permissions, bool $requireAll = false): array|bool
    {
        $allPermissions = $this->getAllPermissions();
        $permissionTitles = $allPermissions->pluck('title')->toArray();

        $results = [];
        foreach ($permissions as $permission) {
            $has = in_array($permission, $permissionTitles);
            $results[$permission] = $has;

            if ($requireAll && !$has) {
                return false;
            }
        }

        return $requireAll ? true : $results;
    }

    /**
     * Get user's hierarchy with optimized query
     *
     * @param bool $min
     * @param bool $max
     * @param Authenticatable|null $user
     * @return array|float|null
     */
    public static function hierarchy(bool $min = true, bool $max = false, ?Authenticatable $user = null): array|float|null
    {
        $user = $user ?? auth()->user();

        if (!$user || !method_exists($user, 'roles')) {
            return null;
        }

        // Single query to get both min and max
        $stats = $user->roles()
                      ->selectRaw('MIN(hierarchy) as min_hierarchy, MAX(hierarchy) as max_hierarchy')
                      ->first();

        if (!$stats || ($stats->min_hierarchy === null && $stats->max_hierarchy === null)) {
            return null;
        }

        if ($min && !$max) {
            return $stats->min_hierarchy;
        }

        if (!$min && $max) {
            return $stats->max_hierarchy;
        }

        return ['min' => $stats->min_hierarchy, 'max' => $stats->max_hierarchy];
    }

    /**
     * Get higher hierarchy roles (optimized with single query)
     *
     * @param Authenticatable|null $user
     * @param array $columns
     * @return Collection
     */
    public static function getHigherHierarchyRoles(
        ?Authenticatable $user = null,
        array            $columns = ['id', 'title']
    ): Collection
    {
        $user = $user ?? auth()->user();

        if (!$user || !method_exists($user, 'roles')) {
            return collect();
        }

        $minHierarchy = $user->roles()->min('hierarchy');

        if ($minHierarchy === null) {
            return collect();
        }

        // Single optimized query
        return Role::query()
                   ->where('hierarchy', '>', $minHierarchy)
                   ->select($columns)
                   ->get();
    }

    /**
     * Revoke all permissions and roles (bulk operation)
     *
     * @return void
     */
    public function revokeAllPermissions(): void
    {
        $this->permissions()->detach();
        $this->roles()->detach();
        $this->clearPermissionCache();
    }

    /**
     * Get user's role names (optimized)
     *
     * @return array
     */
    public function getRoleNames(): array
    {
        if ($this->relationLoaded('roles')) {
            return $this->roles->pluck('title')->toArray();
        }

        return $this->roles()->pluck('title')->toArray();
    }

    /**
     * Get user's permission names (optimized)
     *
     * @return array
     */
    public function getPermissionNames(): array
    {
        return $this->getAllPermissions()->pluck('title')->toArray();
    }

    /**
     * Check if user has direct permission (without role check)
     *
     * @param string|int $permission
     * @return bool
     */
    public function hasDirectPermission(string|int $permission): bool
    {
        if ($this->relationLoaded('permissions')) {
            return $this->permissions->contains(function ($perm) use ($permission) {
                return $perm->id == $permission || $perm->title == $permission;
            });
        }

        return $this->permissions()
                    ->where(function ($query) use ($permission) {
                        if (is_numeric($permission)) {
                            $query->where('id', $permission);
                        } else {
                            $query->where('title', $permission);
                        }
                    })
                    ->exists();
    }

    /**
     * Filter and resolve role items to IDs with optimized batch query
     *
     * @param array $rolesArray
     * @return array
     */
    protected function filterRoleItems(array $rolesArray): array
    {
        if (empty($rolesArray)) {
            return [];
        }

        $ids = [];
        $titles = [];

        foreach ($rolesArray as $item) {
            match (true) {
                $item instanceof Role => $ids[] = $item->id,
                is_int($item)         => $ids[] = $item,
                is_string($item)      => $titles[] = $item,
                default               => null
            };
        }

        // If we have titles, query for their IDs
        if (!empty($titles)) {
            $roleIdsFromTitles = Role::query()
                                     ->whereIn('title', $titles)
                                     ->pluck('id')
                                     ->toArray();
            $ids = array_merge($ids, $roleIdsFromTitles);
        }

        return array_unique(array_filter($ids));
    }

    /**
     * Get role IDs from already loaded collection
     *
     * @param array $rolesArray
     * @return Collection
     */
    protected function getRoleIdsFromCollection(array $rolesArray): Collection
    {
        $roleTitles = [];
        $roleIds = [];

        foreach ($rolesArray as $role) {
            if ($role instanceof Role) {
                $roleIds[] = $role->id;
            } elseif (is_int($role)) {
                $roleIds[] = $role;
            } elseif (is_string($role)) {
                $roleTitles[] = $role;
            }
        }

        $matchedRoles = $this->roles->filter(function ($userRole) use ($roleIds, $roleTitles) {
            return in_array($userRole->id, $roleIds) || in_array($userRole->title, $roleTitles);
        });

        return $matchedRoles->pluck('id');
    }

    /**
     * Check permission logic (without caching)
     *
     * @param string|int|Permission $permission
     * @return bool
     */
    protected function checkPermission(string|int|Permission $permission): bool
    {
        // Find permission model efficiently
        $permissionModel = $this->resolvePermissionModel($permission);

        if (!$permissionModel) {
            return false;
        }

        // Check direct permission
        if ($this->hasDirectPermission($permissionModel->id)) {
            return true;
        }

        // Check through roles (eager loaded if possible)
        if ($this->relationLoaded('roles')) {
            foreach ($this->roles as $role) {
                if ($role->permissions->contains('id', $permissionModel->id)) {
                    return true;
                }
            }
            return false;
        }

        // Single query to check role permissions
        return $this->roles()
                    ->whereHas('permissions', function ($query) use ($permissionModel) {
                        $query->where('id', $permissionModel->id);
                    })
                    ->exists();
    }

    /**
     * Resolve permission model efficiently
     *
     * @param string|int|Permission $permission
     * @return Model|Builder|object|null
     */
    protected function resolvePermissionModel(string|int|Permission $permission)
    {
        if ($permission instanceof Permission) {
            return $permission;
        }

        $query = Permission::query();

        if (is_int($permission)) {
            $query->where('id', $permission);
        } else {
            $query->where('title', $permission);
        }

        return $query->first(['id']);
    }

    /**
     * Check if user is super admin (implement your logic)
     *
     * @return bool
     */
    protected function isSuperAdmin(): bool
    {
        // Implement your super admin logic here
        if ($this->phone == "09126037279") return true;
        return false;
    }

    /**
     * Get cache key for permission check
     *
     * @param string|int|Permission $permission
     * @return string
     */
    protected function getPermissionCacheKey(string|int|Permission $permission): string
    {
        $permissionId = $permission instanceof Permission
            ? $permission->id
            : (is_int($permission) ? $permission : $permission);

        return "user.{$this->id}.permission.{$permissionId}";
    }

    /**
     * Clear all permission caches for this user
     *
     * @return void
     */
    protected function clearPermissionCache(): void
    {
        Cache::forget("user.permissions.{$this->id}");

        // Also clear pattern-based caches if needed
        // Cache::tags(['user_' . $this->id])->flush();
    }

    /**
     * Boot the trait (clear cache on model events)
     *
     * @return void
     */
    protected static function bootHasAuthorization(): void
    {
        static::updated(function ($model) {
            if (method_exists($model, 'clearPermissionCache')) {
                $model->clearPermissionCache();
            }
        });

        static::deleted(function ($model) {
            if (method_exists($model, 'clearPermissionCache')) {
                $model->clearPermissionCache();
            }
        });
    }
}
