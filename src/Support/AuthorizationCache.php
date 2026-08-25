<?php

namespace Teksite\Authorize\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Teksite\Authorize\Models\Permission;
use Teksite\Authorize\Models\Role;

class AuthorizationCache
{
    public const string PREFIX = 'authorize';

    public const string GATE_PERMISSIONS_KEY = 'authorize:permissions:gates';



    public static function forgetModel(Model $model): void
    {
        Cache::forget(self::permissionKey($model));

        Cache::forget(self::roleKey($model));

        Cache::forget(self::hierarchyKey($model));
    }

    public static function forgetPermissionGates(): void
    {
        Cache::forget(self::GATE_PERMISSIONS_KEY);
    }

    public static function forgetPermission(Permission $permission): void
    {
        self::forgetPermissionGates();

        $permissionId = $permission->getKey();

        DB::table('auth_permission_models')
          ->where('permission_id', $permissionId)
          ->get()
          ->each(function ($pivot) {
              self::forgetMorphModel(
                  $pivot->model_type,
                  $pivot->model_id
              );
          });


        DB::table('auth_permission_role')
          ->where('permission_id', $permissionId)
          ->pluck('role_id')
          ->each(function ($roleId) {
              self::forgetRoleModels($roleId);
          });
    }

    public static function forgetRole(Role $role): void
    {
        self::forgetRoleModels($role->getKey());
    }

    public static function forgetRoleModels(int $roleId): void
    {
        DB::table('auth_role_models')
          ->where('role_id', $roleId)
          ->get()
          ->each(function ($pivot) {
              self::forgetMorphModel(
                  $pivot->model_type,
                  $pivot->model_id
              );
          });
    }

    public static function forgetMorphModel(string  $type, int|string $id): void
    {
        $model = new $type;

        $model->setAttribute($model->getKeyName(), $id);

        self::forgetModel($model);
    }

    /**
     * Forget caches of models directly assigned to a permission.
     */
    public static function forgetPermissionModels(int $permissionId): void
    {
        DB::table('auth_permission_models')
          ->where('permission_id', $permissionId)
          ->get()
          ->each(function ($pivot) {
              self::forgetMorphModel(
                  $pivot->model_type,
                  $pivot->model_id
              );
          });
    }

    /**
     * Permission <-> Role pivot changed.
     */
    public static function forgetPermissionRolePivot(
        int $permissionId,
        int $roleId
    ): void {
        self::forgetPermissionModels($permissionId);
        self::forgetRoleModels($roleId);
    }

    /**
     * Role <-> Model pivot changed.
     */
    public static function forgetRoleModelPivot(int $roleId): void
    {
        self::forgetRoleModels($roleId);
    }


    public static function permissionKey(Model $model): string
    {
        return sprintf('authorize:permissions:%s:%s', $model->getMorphClass(), $model->getKey());
    }

    public static function roleKey(Model $model): string
    {
        return sprintf('authorize:roles:%s:%s', $model->getMorphClass(), $model->getKey());
    }

    public static function hierarchyKey(Model $model): string
    {
        return sprintf('authorize:hierarchy:%s:%s', $model->getMorphClass(), $model->getKey());
    }

}
