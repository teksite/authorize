<?php

namespace Teksite\Authorize;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Teksite\Authorize\Console\AuthInstall;
use Teksite\Authorize\Models\Permission;


class GateServiceProvider extends ServiceProvider
{

    protected const int CACHE_TTL = 86400;

    protected const string PERMISSIONS_CACHE_KEY = 'authorize.permissions.gates';

    /**
     * Boot the application events.
     */
    public function boot(): void
    {
        $this->bootGates();
    }


    public function bootGates(): void
    {
        if (!config('authorize.boot_gates', true)) return;

        if ($this->app->runningInConsole() && !config('authorize.boot_gates_in_console', false)) return;

        if (!$this->validateDatabaseTables())  return;


        $permissions = $this->loadPermissions();

        if (empty($permissions)) return;

        $this->registerGates($permissions);
    }


    /**
     * Validate required database tables exist.
     */
    protected function validateDatabaseTables(): bool
    {
        $requiredTables = ['auth_permissions', 'auth_roles', 'auth_permission_role'];

        foreach ($requiredTables as $table) {
            if (!Schema::hasTable($table)) {
                Log::debug('Authorize: Table not found', ['table' => $table]);
                return false;
            }
        }
        return true;
    }

    /**
     * Load permissions with caching and error handling.
     */
    protected function loadPermissions(): array
    {
        try {
            if (config('authorize.cache_enabled', true)) {
                return Cache::remember(
                    self::PERMISSIONS_CACHE_KEY,
                    config('authorize.cache_ttl', self::CACHE_TTL),
                    fn() => $this->fetchPermissionsFromDatabase()
                );
            }
            return $this->fetchPermissionsFromDatabase();
        } catch (\Exception $e) {
            Log::error('Authorize: Failed to load permissions', [
                'error' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null,
            ]);

            return [];
        }
    }

    /**
     * Fetch permissions directly from database.
     */
    protected function fetchPermissionsFromDatabase(): array
    {
        try {
            return Permission::query()
                             ->select(['title'])
                             ->pluck('title')
                             ->filter()
                             ->values()
                             ->toArray();
        } catch (\Exception $e) {
            Log::error('Authorize: Database query failed', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Register multiple gates efficiently.
     */
    protected function registerGates(array $permissions): void
    {
        Gate::before(function ($user, $ability) use ($permissions) {
            if (!in_array($ability, $permissions, true)) {
                return null;
            }

            $superAdmin = config('authorize.super_admin_role', null);
            if ($superAdmin && ($user->hasRole($superAdmin) ?? false)) {
                return true;
            }

            return null;
        });

        // Register individual gates for precise control
        foreach ($permissions as $permission) {
            Gate::define($permission, function ($user) use ($permission) {
                try {
                    return $user->hasPermission($permission);
                } catch (\Exception $e) {
                    Log::error('Authorize: Gate check failed', [
                        'permission' => $permission,
                        'user_id'    => $user->id ?? null,
                        'error'      => $e->getMessage(),
                    ]);
                    return false;
                }
            });
        }
    }


}
