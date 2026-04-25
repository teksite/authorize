<?php

namespace Teksite\Authorize\Traits;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Teksite\Authorize\Models\Permission;
use Teksite\Authorize\Models\Role;

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
     * @param array $permissions
     * @param bool $detaching
     * @return void
     */
    public function syncPermissions(array $permissions, bool $detaching = true): void
    {
        $detaching
            ? $this->permissions()->sync($permissions)
            : $this->permissions()->syncWithoutDetaching($permissions);

    }

    /**
     * Assign roles to the model.
     *
     * @param Role|Role[]|string|string[] $roles
     * @param bool $detaching
     * @return array|null
     */
    public function assignRole(array|int|string|Role $roles, bool $detaching = true): ?array
    {
        $rolesArray = is_array($roles) ? $roles : [$roles];

        $filteredIds = $this->filterRoleItems($rolesArray);

        if (empty($roleIds)) return null;

        // Sync roles with optional detaching
        return $detaching ? $this->roles()->sync($filteredIds) : $this->roles()->syncWithoutDetaching($filteredIds);
    }

    /**
     * @param string|int|array|Role $roles
     * @param bool $any
     * @return bool
     */
    public function hasRole(string|int|array|Role $roles, bool $any = true): bool
    {
        $rolesArray = is_array($roles) ? $roles : [$roles];

        $filteredIds = $this->filterRoleItems($rolesArray);

        if (empty($filteredIds)) return false;

        $count = $this->roles()->whereIn('id', $filteredIds)->count();
        return $any ? $count > 0 : $count === count($rolesArray);


    }

    /**
     * @param string|int|Permission $permission
     * @return bool
     */
    public function hasPermission(string|int|Permission $permission): bool
    {
        if (is_string($permission)) {
            $permissionModel = Permission::query()->where('title', $permission)->with('roles', function ($query) {
                $query->select(['title', 'id']);
            })->first('id');
        } elseif (is_int($permission)) {
            $permissionModel = Permission::query()->where('id', $permission)->with('roles', function ($query) {
                $query->select(['title', 'id']);
            })->first('id');
        } else {
            $permissionModel = $permission;
        }
        if (!$permissionModel) return false;
        return $this->permissions->contains('id', $permission->id) || $this->hasRole($permission->roles);
    }

    /**
     * @return mixed
     */
    public function allPermission(): mixed
    {
        $directPermissions = $this->permissions;
        $rolePermissions = $this->roles->flatMap(fn($role) => $role->permissions);

        return $directPermissions->merge($rolePermissions)->unique('id');

    }


    /**
     * @param bool $min
     * @param bool $max
     * @param Authenticatable|null $user
     * @return array|float|null
     */
    public static function hierarchy(bool $min = true, bool $max = false, null|Authenticatable $user = null): array|float|null
    {
        $user = $user ?? auth()->user();
        if (!$user) return null;

        $roles = $user->roles()->get(['hierarchy']);

        $minHierarchy = $roles->min('hierarchy');
        $maxHierarchy = $roles->max('hierarchy');

        if ($min && $max === false) {
            return $minHierarchy;
        } elseif ($min === false && $max) {
            return $maxHierarchy;
        }
        return ['min' => $minHierarchy, 'max' => $maxHierarchy];
    }


    /**
     * @param Authenticatable|null $user
     * @return \Illuminate\Database\Eloquent\Collection|Collection|null
     */
    public static function hierarchyRoles(?Authenticatable $user = null): null|\Illuminate\Database\Eloquent\Collection|Collection
    {
        $user = $user ?? auth()->user();
        if (!$user) return null;
        $minHierarchy = $user->roles()->min('hierarchy');

        if ($minHierarchy === null) return collect();

        return Role::query()->where('hierarchy', '>', $minHierarchy)->select(['id', 'title'])->get();
    }

    /**
     * @param array|Role $rolesArray
     * @return array
     */
    public function filterRoleItems(array|Role $rolesArray): array
    {
        $ids = [];
        $itemsToCheck = [];

        foreach ($rolesArray as $item) {
            if ($item instanceof Role) $ids[] = $item->id;
            elseif (is_string($item)) $itemsToCheck['titles'][] = $item;
            elseif (is_int($item)) $itemsToCheck['ids'][] = $item;
        }
        $rolesId = Role::query()
                       ->whereIn('id', $itemsToCheck['ids'] ?? [])
                       ->orWhereIn('title', $itemsToCheck['titles'] ?? [])
                       ->select(['id'])
                       ->get()->toArray();

        return collect($rolesId)->merge($ids)->flatten()->filter()->unique()->toArray();
    }


}


