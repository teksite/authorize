<?php

namespace Teksite\Authorize\Traits;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Lareon\CMS\App\Models\User;
use Teksite\Authorize\Models\Permission;
use Teksite\Authorize\Models\Role;

trait HasAuthorization
{

    public function permissions()
    {
        return $this->morphToMany(Permission::class, 'model', 'auth_permission_models');

    }


    public function roles()
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
        if ($detaching) {
            $this->permissions()->sync($permissions);
        } else {
            $this->permissions()->syncWithoutDetaching($permissions);
        }
    }

    /**
     * Assign roles to the model.
     *
     * @param Role|Role[]|string|string[] $roles
     * @param bool $detaching
     * @return void
     */
    public function assignRole(array|string|Role $roles, bool $detaching = true): void
    {
        $roleIds = [];
        // Ensure roles are an array
        $roles = is_array($roles) ? $roles : [$roles];

        foreach ($roles as $role) {
            if ($role instanceof Role) {
                $roleIds[] = $role->id;
            }elseif (is_numeric($role)) {
                $role = Role::query()->where('id', $role)->first(['id']);
                if ($role) $roleIds[]= $role->id;
            }else{
                $role = Role::query()->where('title', $role)->first(['id']);
                if ($role) $roleIds[]= $role->id;
            }
        }

        // Sync roles with optional detaching
        $detaching ? $this->roles()->sync($roleIds) : $this->roles()->syncWithoutDetaching($roleIds);
    }

    /**
     * @param string|int|array|Role|Collection $roles
     * @param bool $any
     * @return bool
     */
    public function hasRole(string|int|array|Role|Collection $roles, bool $any = true): bool
    {
        // Convert to an array if it's not already
        $rolesArr = is_array($roles) || $roles instanceof Collection ? $roles : [$roles];

        // Collect role IDs
        $roleIds = collect($rolesArr)->map(fn($role) => match (true) {
            is_string($role) => Role::query()->where('title', $role)->first('id')?->id,
            is_int($role) => $role,
            $role instanceof Role => $role->id,
            default => null
        })->filter()->all(); // Remove null values

        if (empty($roleIds))  return false;

        // If $any is true, check if at least one role exists
        // If $any is false, check if all roles exist
        $query = $this->roles()->whereIn('id', $roleIds);

        return $any ? $query->exists() : $query->count() === count($roleIds);

    }

    /**
     * @param string|int|Permission $permission
     * @return bool
     */
    public function hasPermission(string|int|Permission $permission): bool
    {

        if (is_string($permission)) {
            $permission = Permission::query()->where('title', $permission)->with('roles', function ($query) {
                $query->select(['title', 'id']);
            })->first('id');
        } elseif (is_int($permission)) {
            $permission = Permission::query()->where('id', $permission)->with('roles', function ($query) {
                $query->select(['title', 'id']);
            })->first('id');
        }
        if ($permission->exists === false) return false;
        return $this->permissions->contains('id', $permission->id) || $this->hasRole($permission->roles);
    }

    /**
     * @return mixed
     */
    public function allPermission()
    {
        return $this->roles->map(function ($role) {
            return $role->permissions;
        })->flatten()->merge($this->permissions)->unique('id');
    }


    /**
     * @param bool $min
     * @param bool $max
     * @param User|null $user
     * @return array|float|null
     */
    public static function hierarchy(bool $min = true, bool $max = false ,null|User $user = null): array|float|null
    {
        $user =$user ?? auth()->user(); if (!$user) return null;

        $hierarchy['min'] = $user->roles()->min('hierarchy');
        $hierarchy['max'] = $user->roles()->max('hierarchy');
        if ($min && $max === false) {
            return $hierarchy['min'];
        } elseif ($min === false && $max) {
            return $hierarchy['max'];
        }
        return $hierarchy;
    }


    /**
     * @param User|null $user
     * @return \Illuminate\Database\Eloquent\Collection|null
     */
    public static function hierarchyRoles(null|User $user = null): ?\Illuminate\Database\Eloquent\Collection
    {
        $user =$user ?? auth()->user(); if (!$user) return null;
        $hierarchy['min'] = $user->roles()->min('hierarchy');
        return Role::query()->where('hierarchy','>',$hierarchy)->select(['id' ,'title'])->get();
    }


}


