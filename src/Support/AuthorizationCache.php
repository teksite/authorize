<?php

namespace Teksite\Authorize\Support;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Psr\SimpleCache\InvalidArgumentException;


class AuthorizationCache
{
    public const string PREFIX = 'authorize';

    public const string GATE_PERMISSIONS_KEY = 'authorize:permissions:gates';

    public const int DEFAULT_TTL = 86400;

    private const string EPOCH_KEY = 'authorize:epoch';

    private const int CHUNK_SIZE = 1000;

    public static function ttl(): int
    {
        return (int)config('authorize.cache_ttl', self::DEFAULT_TTL);
    }

    /**
     * Invalidate every cached model payload in O(1).
     * Entries carry the epoch they were computed under; a mismatch is a miss.
     */
    public static function bump(): void
    {
        Cache::forever(self::EPOCH_KEY, Str::random(16));
    }

    /**
     * Epoch-aware remember(). The epoch is read BEFORE the callback runs, so a
     * value computed concurrently with an invalidation is stored under the old
     * epoch and can never be served afterward.
     */
    public static function remember(string $key, Closure $callback, ?int $ttl = null): mixed
    {
        $epoch = self::epoch();
        $entry = Cache::get($key);

        if (is_array($entry) && ($entry['epoch'] ?? null) === $epoch && array_key_exists('data', $entry)) return $entry['data'];

        $data = $callback();

        Cache::put($key, ['epoch' => $epoch, 'data' => $data], $ttl ?? self::ttl());

        return $data;
    }


    private static function epoch(): string
    {
        $epoch = Cache::get(self::EPOCH_KEY);

        if (is_string($epoch) && $epoch !== '') return $epoch;

        Cache::add(self::EPOCH_KEY, Str::random(16));

        $epoch = Cache::get(self::EPOCH_KEY);

        return is_string($epoch) ? $epoch : '0';
    }


    /**
     * @throws InvalidArgumentException
     */
    public static function forgetModel(Model $model): void
    {
        $id = $model->getKey();

        if ($id === null) return;

        Cache::deleteMultiple(self::keysFor($model->getMorphClass(), $id));
    }


    public static function forgetPermissionGates(): void
    {
        Cache::forget(self::GATE_PERMISSIONS_KEY);
    }


    public static function forgetPermission(): void
    {
        self::forgetPermissionGates();
        self::bump();
    }


    public static function forgetRole(): void
    {
        self::bump();
    }


    /**
     * Targeted flush for holders of a role. Observers use bump() instead.
     *
     * @throws InvalidArgumentException
     */
    public static function forgetRoleModels(int $roleId): void
    {
        self::forgetFromQuery(DB::table('auth_role_models')->where('role_id', $roleId));
    }


    /**
     * Targeted flush for models that hold a permission directly.
     *
     * @throws InvalidArgumentException
     */
    public static function forgetPermissionModels(int $permissionId): void
    {
        self::forgetFromQuery(DB::table('auth_permission_models')->where('permission_id', $permissionId));
    }


    /**
     * $type is the value stored in model_type (FQCN or morph-map alias).
     * It is used as-is: never instantiated.
     *
     * @throws InvalidArgumentException
     */
    public static function forgetMorphModel(string $type, int|string $id): void
    {
        Cache::deleteMultiple(self::keysFor($type, $id));
    }


    /**
     * Permission <-> Role pivot changed: invalidates every cached payload.
     */
    public static function forgetPermissionRolePivot(): void
    {
        self::bump();
    }


    /**
     * @throws InvalidArgumentException
     */
    public static function forgetRoleModelPivot(int $roleId): void
    {
        self::forgetRoleModels($roleId);
    }


    public static function permissionKey(Model $model): string
    {
        return self::keyFor('permissions', $model->getMorphClass(), $model->getKey());
    }


    public static function roleKey(Model $model): string
    {
        return self::keyFor('roles', $model->getMorphClass(), $model->getKey());
    }


    public static function hierarchyKey(Model $model): string
    {
        return self::keyFor('hierarchy', $model->getMorphClass(), $model->getKey());
    }


    private static function keyFor(string $bucket, string $type, int|string|null $id): string
    {
        return sprintf('%s:%s:%s:%s', self::PREFIX, $bucket, $type, $id);
    }


    /** @return string[] */
    private static function keysFor(string $type, int|string|null $id): array
    {
        return [
            self::keyFor('permissions', $type, $id),
            self::keyFor('roles', $type, $id),
            self::keyFor('hierarchy', $type, $id),
        ];
    }

    /**
     * @throws InvalidArgumentException
     */
    private static function forgetFromQuery(Builder $query): void
    {
        $query->select(['model_type', 'model_id'])
              ->orderBy('model_type')
              ->orderBy('model_id')
              ->chunk(
                  self::CHUNK_SIZE, function (Collection $rows): void {
                  $keys = [];

                  foreach ($rows as $row) {
                      array_push($keys, ...self::keysFor($row->model_type, $row->model_id));
                  }

                  Cache::deleteMultiple($keys);
              });
    }

}
