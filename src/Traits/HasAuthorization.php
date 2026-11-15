<?php

namespace Teksite\Authorize\Traits;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
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
        $ids = $this->resolvePermissionIds($permissions);

        $result = $detaching
            ? $this->permissions()->sync($ids)
            : $this->permissions()->syncWithoutDetaching($ids);

        $this->unsetRelation('permissions');
        $this->unsetRelation('roles');
        $this->clearAuthorizationCache();

        return $result;
    }


    /**
     * Sync roles
     */
    public function assignRole(array|int|string|Role $roles, bool $detaching = true): ?array
    {

        $ids = $this->resolveRoleIds($roles);

        $result = $detaching
            ? $this->roles()->sync($ids)
            : $this->roles()->syncWithoutDetaching($ids);

        $this->unsetRelation('roles');
        $this->clearAuthorizationCache();

        return $result;

    }

    /**
     * Determine whether the model has one or more roles.
     */
    public function hasRole(string|int|array|Role $roles, bool $any = true): bool
    {
        [$ids, $titles] = $this->splitAuthorizationIdentifiers($roles, Role::class);

        return $this->ownsAuthorizationIdentifiers($this->getDirectRoles(), $ids, $titles, $any);
    }

    /**
     * does the model have permission(s)
     */
    public function hasPermission(string|int|array|Permission $permissions, bool $any = true): bool
    {

        [$ids, $titles] = $this->splitAuthorizationIdentifiers($permissions, Permission::class);

        if (($ids !== [] || $titles !== [])
            && $this->ownsAuthorizationIdentifiers($this->getAllPermissions(false), $ids, $titles, $any)) {
            return true;
        }

        return $this->isSuperAdmin();
    }

    /**
     * Filter and resolve permission items to IDs
     */
    protected function resolvePermissionIds(array|string|int|Permission $permissions): array
    {
        return $this->resolveAuthorizationIds(Permission::class, $permissions);
    }

    /**
     * Filter and resolve roles items to IDs
     */
    protected function resolveRoleIds(array|int|string|Role $roles): array
    {
        return $this->resolveAuthorizationIds(Role::class, $roles);
    }

    /**
     * Resolve ids/titles/instances to existing row ids (used for writes only).
     */
    protected function resolveAuthorizationIds(string $modelClass, array|string|int|Model $items): array
    {
        [$ids, $titles] = $this->splitAuthorizationIdentifiers($items, $modelClass);

        if ($ids === [] && $titles === []) return [];

        return $modelClass::query()->where(function ($query) use ($ids, $titles) {
            if ($ids !== []) $query->whereIn('id', $ids);
            if ($titles !== []) $query->orWhereIn('title', $titles);
        })->pluck('id')->all();

    }

    /**
     * @return array{0: int[], 1: string[]}
     */
    protected function splitAuthorizationIdentifiers(array|string|int|Model $items, string $modelClass): array
    {
        $ids = [];
        $titles = [];

        foreach (is_array($items) ? $items : [$items] as $item) {
            if ($item instanceof $modelClass) {
                $ids[] = (int)$item->getKey();
            } elseif (is_int($item)) {
                $ids[] = $item;
            } elseif (is_string($item)) {
                if (is_numeric($item)) {
                    $ids[] = (int)$item;
                } else {
                    $titles[] = $item;
                }
            }
        }

        return [array_values(array_unique($ids)), array_values(array_unique($titles))];
    }


    /**
     * Match against owned [id => title]. In "all" mode EVERY requested identifier
     * must be owned: unknown identifiers count as missing (fail closed).
     *
     * @param array<int, string> $owned
     */
    protected function ownsAuthorizationIdentifiers(array $owned, array $ids, array $titles, bool $any): bool
    {
        $requested = count($ids) + count($titles);

        if ($requested === 0) return false;

        $matched = 0;

        foreach ($ids as $id) {
            if (isset($owned[$id])) $matched++;
        }

        if ($titles !== []) {
            $ownedTitles = array_flip($owned);

            foreach ($titles as $title) {
                if (isset($ownedTitles[$title])) $matched++;
            }
        }

        return $any ? $matched > 0 : $matched === $requested;
    }


    /**
     * [id => title] of direct + role-inherited permissions.
     */
    public function getAllPermissions(bool $onlyIds = true): array
    {
        $permissions = $this->rememberAuthorizationCache($this->getPermissionCacheKey(), function (): array {
            $direct = $this->permissions()->pluck('title', 'id')->all();

            $viaRoles = Permission::query()->whereIn('id', function ($query) {
                $query->select('permission_id')
                      ->from('auth_permission_role')
                      ->whereIn('role_id', function ($sub) {
                          $sub->select('role_id')
                              ->from('auth_role_models')
                              ->where('model_type', $this->getMorphClass())
                              ->where('model_id', $this->getKey());
                      });
            })->pluck('title', 'id')->all();

            return $direct + $viaRoles;
        });

        return $onlyIds ? array_keys($permissions) : $permissions;
    }

    public function getDirectPermissions(): array
    {
        return $this->permissions()->pluck('title', 'id')->toArray();
    }

    public function getPermissionsByRoles(): array
    {
        $this->loadMissing('roles.permissions');

        $permissions = [];

        foreach ($this->roles as $role) {
            $permissions[$role->title] = $role->permissions->pluck('title', 'id')->toArray();
        }

        return $permissions;
    }

    public function getDirectRoles(bool $onlyIds = false): array
    {
        $roles = $this->rememberAuthorizationCache(
            $this->getRoleCacheKey(),
            fn(): array => $this->roles()->pluck('title', 'id')->all()
        );

        return $onlyIds ? array_keys($roles) : $roles;
    }

    public function isSuperAdmin(): bool
    {
        $role = config('authorize.super_admin_role');

        return $role !== null && $this->hasRole($role);
    }


    /**
     * get hierarchy of the model
     */
    public function hierarchy(bool $min = true, bool $max = false): array|float|null
    {
        $stats = $this->rememberAuthorizationCache($this->getHierarchyCacheKey(), function (): array {
            $values = $this->roles()->pluck('hierarchy')->map(fn($v) => (int)$v);

            return $values->isEmpty() ? ['min' => null, 'max' => null] : ['min' => $values->min(), 'max' => $values->max()];
        });

        if ($stats['min'] === null) return null;

        if ($min && !$max) return (float)$stats['min'];

        if (!$min && $max) return (float)$stats['max'];

        return ['min' => $stats['min'], 'max' => $stats['max']];
    }

    /**
     * Check hierarchy access against another authorization model.
     */
    public function canAccessModelByHierarchy(Model $model): bool|null
    {
        if (!method_exists($model, 'hierarchy')) throw new InvalidArgumentException('Model must use HasAuthorization: '.$model::class);

        $myHierarchy = $this->hierarchy(min: true, max: false);

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
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
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
        return AuthorizationCache::ttl();
    }


    /**
     * Clear cache whenever the authorization model is saved.
     */
    protected static function bootHasAuthorization(): void
    {
        static::saved(static fn(Model $model) => $model->clearAuthorizationCache());

        static::deleted(static function (Model $model) {
            $model->clearAuthorizationCache();

            if (!config('authorize.detach_on_delete', true)) return;

            if (method_exists($model, 'isForceDeleting') && !$model->isForceDeleting()) return;

            $model->roles()->detach();
            $model->permissions()->detach();
        });
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


    protected function rememberAuthorizationCache(string $key, \Closure $callback): mixed
    {

        if (!config('authorize.cache_enabled', true)) return $callback();

        return AuthorizationCache::remember($key, $callback, $this->getCacheDuration());
    }

}
