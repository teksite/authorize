<?php

namespace Teksite\Authorize;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Teksite\Authorize\Console\AuthInstall;
use Teksite\Authorize\Models\Permission;


class AuthorizeServiceProvider extends ServiceProvider
{

    protected const int CACHE_TTL = 86400;

    protected const string PERMISSIONS_CACHE_KEY = 'authorize.permissions.gates';

    public function register(): void
    {
        $this->registerConfigs();
    }

    /**
     * Boot the application events.
     */
    public function boot(): void
    {
        $this->bootGates();
        $this->bootCommands();
        $this->bootPublishing();
    }


    public function bootGates(): void
    {
        if (!config('authorize.boot_gates', true)) {
            return;
        }

        if ($this->app->runningInConsole() && !config('authorize.boot_gates_in_console', false)) {
            return;
        }

        if (!$this->validateDatabaseTables()) {
            return;
        }

        $permissions = $this->loadPermissions();

        if (empty($permissions)) {
            return;
        }

        // Register gates in batch for better performance
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
            // Try to get from cache first
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
        // Register gates in bulk using a single closure for better memory usage
        Gate::before(function ($user, $ability) use ($permissions) {
            // Skip if ability is not in our permissions list
            if (!in_array($ability, $permissions, true)) {
                return null;
            }

            $superAdmin = config('authorize.super_admin_role', null);
            // Super admin bypass
            if ($superAdmin && $user->hasRole($superAdmin) ?? false) {
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

    /**
     * Boot console commands.
     */
    protected function bootCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                AuthInstall::class,
            ]);
        }
    }


    protected function registerConfigs(): void
    {
        $configPath = config_path('authorize.php');
        $this->mergeConfigFrom(file_exists($configPath) ? $configPath : __DIR__ . '/config/authorize.php', 'authorize');
    }

    /**
     * Boot publishing configuration and migrations.
     */
    protected function bootPublishing(): void
    {
        $this->publishes([
            __DIR__ . '/../config/authorize.php' => config_path('authorize.php'),
        ], 'authorize-config');

        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], 'authorize-migrations');
    }


}
